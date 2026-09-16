<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provision (or refresh) a leader with the `admin` role on one tenant, so the
 * admin app flow can be tested end to end.
 *
 * By default the admin is scoped to ONE governorship (a constituency) — the one
 * with the most Bacentas — so it behaves like a real admin: it only sees that
 * governor's Bacentas, members and submissions, not the whole church. Use
 * --list to see the governorships and --group to pick a specific one.
 *
 *   php artisan flock:make-test-admin gochurch.church-stack.com
 *   php artisan flock:make-test-admin gochurch.church-stack.com --list
 *   php artisan flock:make-test-admin gochurch.church-stack.com --group="North Constituency"
 *   php artisan flock:make-test-admin gochurch.church-stack.com --group=42 --whole-church
 *
 * Idempotent: re-running resets the test leader's password (and reprints it).
 * The leader + its throwaway member are clearly named and safe to delete after.
 */
class MakeTestAdmin extends Command
{
    protected $signature = 'flock:make-test-admin {domain : tenant domain, e.g. gochurch.church-stack.com}
        {--username=campadmin : the leader login username}
        {--group= : governorship to scope to — a group id or a name (default: the governorship with the most Bacentas)}
        {--whole-church : scope to the biggest group overall instead of one governorship}
        {--list : just list the governorships (constituencies) with Bacenta counts, then exit}';

    protected $description = 'Create/refresh a leader with the admin role on a tenant for testing';

    public function handle(): int
    {
        $domain = $this->argument('domain');
        $tenantId = DB::table('domains')->where('domain', $domain)->value('tenant_id');
        if (! $tenantId) {
            $this->error("No tenant is claimed for domain '{$domain}'.");

            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant {$tenantId} not found.");

            return self::FAILURE;
        }

        $username = $this->option('username') ?: 'campadmin';
        $groupOpt = $this->option('group');
        $wholeChurch = (bool) $this->option('whole-church');
        $listOnly = (bool) $this->option('list');
        $issued = null;
        $rows = null;

        $tenant->run(function () use ($username, $groupOpt, $wholeChurch, $listOnly, &$issued, &$rows) {
            $cellTypeId = (int) GroupType::where('slug', 'cell-group')->value('id');
            // A "governorship" is a constituency (the governor role attaches to it).
            $constituencyTypeIds = GroupType::whereIn('slug', ['constituency', 'governor'])->pluck('id')->all();

            // Load every active group once; count Bacentas per subtree in one pass.
            $all = Group::where('is_active', true)->get(['id', 'parent_id', 'group_type_id', 'name']);
            $childrenBy = [];
            foreach ($all as $g) {
                $childrenBy[$g->parent_id][] = $g;
            }
            $memo = [];
            $subtreeCells = function ($g) use (&$subtreeCells, $childrenBy, $cellTypeId, &$memo) {
                if (isset($memo[$g->id])) {
                    return $memo[$g->id];
                }
                $n = ((int) $g->group_type_id === $cellTypeId) ? 1 : 0;
                foreach ($childrenBy[$g->id] ?? [] as $child) {
                    $n += $subtreeCells($child);
                }

                return $memo[$g->id] = $n;
            };

            if ($listOnly) {
                $rows = $all->filter(fn ($g) => in_array($g->group_type_id, $constituencyTypeIds))
                    ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'bacentas' => $subtreeCells($g)])
                    ->sortByDesc('bacentas')->values()->all();

                return;
            }

            $adminRole = RoleDefinition::where('slug', 'admin')->first();
            if (! $adminRole) {
                $this->error("This tenant has no 'admin' role definition.");

                return;
            }

            // Choose the group to scope the admin to.
            $group = null;
            if ($groupOpt !== null && $groupOpt !== '') {
                $group = is_numeric($groupOpt)
                    ? $all->firstWhere('id', (int) $groupOpt)
                    : $all->first(fn ($g) => stripos($g->name, $groupOpt) !== false);
            } elseif ($wholeChurch) {
                foreach ($all as $g) {
                    if (! $group || $subtreeCells($g) > $subtreeCells($group)) {
                        $group = $g;
                    }
                }
            } else {
                // Default: the governorship (constituency) with the most Bacentas.
                foreach ($all as $g) {
                    if (! in_array($g->group_type_id, $constituencyTypeIds)) {
                        continue;
                    }
                    if (! $group || $subtreeCells($g) > $subtreeCells($group)) {
                        $group = $g;
                    }
                }
                // Fall back to the biggest group if the tenant has no constituencies.
                if (! $group) {
                    foreach ($all as $g) {
                        if (! $group || $subtreeCells($g) > $subtreeCells($group)) {
                            $group = $g;
                        }
                    }
                }
            }

            if (! $group) {
                $this->error('No matching group found to scope the admin to.');

                return;
            }

            $password = Str::password(12, true, true, false);

            $leader = Leader::where('username', $username)->first();
            if ($leader) {
                $leader->password = $password; // hashed by the model mutator
                $leader->is_active = true;
                $leader->save();
                // Keep scope clean: drop any prior admin roles on this test leader.
                LeaderRole::where('leader_id', $leader->id)
                    ->where('role_definition_id', $adminRole->id)->delete();
            } else {
                $member = Member::create([
                    'first_name' => 'Camp',
                    'last_name' => 'Test Admin',
                    'member_type' => 'member',
                    'is_active' => true,
                ]);
                $leader = Leader::create([
                    'member_id' => $member->id,
                    'username' => $username,
                    'password' => $password,
                    'is_active' => true,
                ]);
            }

            LeaderRole::create([
                'leader_id' => $leader->id,
                'role_definition_id' => $adminRole->id,
                'group_id' => $group->id,
                'assigned_at' => now(),
                'is_active' => true,
            ]);

            $issued = [
                'username' => $username,
                'password' => $password,
                'group' => $group->name,
                'bacentas' => $subtreeCells($group),
            ];
        });

        if ($listOnly) {
            if (! $rows) {
                $this->warn('No governorships (constituencies) found on this tenant.');

                return self::SUCCESS;
            }
            $this->table(['Group id', 'Governorship', 'Bacentas'],
                array_map(fn ($r) => [$r['id'], $r['name'], $r['bacentas']], $rows));
            $this->comment('Re-run with --group=<id or name> to scope the test admin to one of these.');

            return self::SUCCESS;
        }

        if (! $issued) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Test admin ready — log into the app with:');
        $this->line("  Username: {$issued['username']}");
        $this->line("  Password: {$issued['password']}");
        $this->line("  Scoped to: {$issued['group']}  ({$issued['bacentas']} Bacentas in reach)");
        $this->newLine();
        $this->comment('Copy the password now — it is hashed on save and cannot be read back.');

        return self::SUCCESS;
    }
}
