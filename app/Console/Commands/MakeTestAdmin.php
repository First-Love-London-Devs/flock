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
 * admin app flow can be tested end to end. Attaches the role to the group with
 * the most Bacentas under it, so the Members list, appointing leaders, and the
 * Submissions tab all have real data in scope.
 *
 *   php artisan flock:make-test-admin gochurch.church-stack.com
 *
 * Idempotent: re-running resets the test leader's password (and reprints it).
 * The leader + its throwaway member are clearly named and safe to delete after.
 */
class MakeTestAdmin extends Command
{
    protected $signature = 'flock:make-test-admin {domain : tenant domain, e.g. gochurch.church-stack.com}
        {--username=campadmin : the leader login username}
        {--group= : group id to scope the admin to (default: the group with the most Bacentas)}';

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
        $issued = null;

        $tenant->run(function () use ($username, $groupOpt, &$issued) {
            $adminRole = RoleDefinition::where('slug', 'admin')->first();
            if (! $adminRole) {
                $this->error("This tenant has no 'admin' role definition.");

                return;
            }

            // The group the admin sits on. Default: the active group whose subtree
            // holds the most Bacentas, so there is plenty in scope to test with.
            // Computed in memory in one pass — a per-group subtree query would be
            // pathological on a big tree (the whole Eurozone lives under gochurch).
            $cellTypeId = (int) GroupType::where('slug', 'cell-group')->value('id');
            $bacentasInScope = 0;

            if ($groupOpt) {
                $group = Group::find((int) $groupOpt);
                if ($group) {
                    $bacentasInScope = Group::whereIn('id', $group->allGroupIds())
                        ->where('group_type_id', $cellTypeId)->where('is_active', true)->count();
                }
            } else {
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
                $group = null;
                foreach ($all as $g) {
                    $n = $subtreeCells($g);
                    if ($n > $bacentasInScope) {
                        $bacentasInScope = $n;
                        $group = $g;
                    }
                }
                $group = $group ?? $all->first();
            }
            if (! $group) {
                $this->error('No group found to scope the admin to.');

                return;
            }

            $password = Str::password(12, true, true, false);

            $leader = Leader::where('username', $username)->first();
            if ($leader) {
                $leader->password = $password; // hashed by the model mutator
                $leader->is_active = true;
                $leader->save();
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

            LeaderRole::updateOrCreate(
                [
                    'leader_id' => $leader->id,
                    'role_definition_id' => $adminRole->id,
                    'group_id' => $group->id,
                ],
                ['assigned_at' => now(), 'is_active' => true],
            );

            $issued = [
                'username' => $username,
                'password' => $password,
                'group' => $group->name,
                'bacentas' => $bacentasInScope,
            ];
        });

        if (! $issued) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Test admin ready — log into the app with:');
        $this->line("  Username: {$issued['username']}");
        $this->line("  Password: {$issued['password']}");
        $this->line("  Scope:    {$issued['group']}  ({$issued['bacentas']} Bacentas in reach)");
        $this->newLine();
        $this->comment('Copy the password now — it is hashed on save and cannot be read back.');

        return self::SUCCESS;
    }
}
