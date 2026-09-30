<?php

namespace App\Console\Commands;

use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Promote a real person to tenant super admin on one church.
 *
 * A super admin is a Leader with an active super-admin LeaderRole attached to
 * NO group (permission level 100). That is what LeaderScopeService::isSuperAdmin
 * reads, and it is what opens every admin endpoint across the whole tenant.
 *
 * This is the bootstrap: from inside the app only an existing super admin can
 * appoint another, so the very first one on each church has to be minted here
 * once. After that, everyone else is done in the app.
 *
 *   php artisan flock:make-super-admin gochurch.church-stack.com --username=king
 *   php artisan flock:make-super-admin gochurch.church-stack.com --member=57
 *   php artisan flock:make-super-admin gochurch.church-stack.com --member="Jane Doe"
 *   php artisan flock:make-super-admin gochurch.church-stack.com --list
 *
 * Idempotent: re-running on someone who is already a super admin changes
 * nothing and says so. Promoting a member who has no login creates one and
 * prints the credentials once.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'flock:make-super-admin
        {domain : tenant domain, e.g. gochurch.church-stack.com}
        {--username= : promote the existing leader with this login username}
        {--member= : promote a member (id or full name); creates a login if they have none}
        {--list : list current super admins on this tenant, then exit}';

    protected $description = 'Grant a leader the tenant-wide super admin role (bootstrap the first one)';

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

        $username = $this->option('username');
        $memberOpt = $this->option('member');
        $listOnly = (bool) $this->option('list');

        $result = null;   // ['username' => .., 'name' => .., 'password' => ?, 'already' => bool]
        $supers = null;   // rows for --list
        $fail = null;     // human error raised inside the tenant closure

        $tenant->run(function () use ($username, $memberOpt, $listOnly, &$result, &$supers, &$fail) {
            // The whole-church role: no group, permission level 100. Guaranteed to
            // exist so the assign below never silently misses (firstOrCreate keeps
            // it idempotent and safe on a church that was seeded long ago).
            $roleDef = RoleDefinition::firstOrCreate(
                ['slug' => 'super-admin'],
                [
                    'name' => 'Super Admin',
                    'permission_level' => 100,
                    'applies_to_group_type_id' => null,
                    'is_active' => true,
                ],
            );

            $isSuper = fn (Leader $l) => $l->leaderRoles()
                ->where('is_active', true)
                ->whereNull('group_id')
                ->where('role_definition_id', $roleDef->id)
                ->exists();

            if ($listOnly) {
                $supers = Leader::whereHas('leaderRoles', function ($q) use ($roleDef) {
                    $q->where('is_active', true)
                        ->whereNull('group_id')
                        ->where('role_definition_id', $roleDef->id);
                })->with('member')->get()->map(fn (Leader $l) => [
                    'username' => $l->username,
                    'name' => $l->member ? trim($l->member->first_name.' '.$l->member->last_name) : '(no member)',
                    'active' => $l->is_active ? 'yes' : 'NO',
                ])->all();

                return;
            }

            // Resolve who we are promoting.
            $leader = null;
            $issuedPassword = null;

            if ($username) {
                $leader = Leader::where('username', $username)->first();
                if (! $leader) {
                    $fail = "No leader with username '{$username}' on this tenant. Try --list, or --member=<id or name>.";

                    return;
                }
            } elseif ($memberOpt !== null && $memberOpt !== '') {
                $member = null;
                if (is_numeric($memberOpt)) {
                    $member = Member::find((int) $memberOpt);
                    if (! $member) {
                        $fail = "No member with id {$memberOpt}.";

                        return;
                    }
                } else {
                    // Prefer an exact full-name match; fall back to a contains
                    // search so a first name alone ("Nancy") resolves when it is
                    // unambiguous, and lists the options by id when it is not.
                    $needle = trim($memberOpt);
                    $exact = Member::whereRaw("TRIM(CONCAT(first_name,' ',last_name)) = ?", [$needle])->get();
                    $candidates = $exact->isNotEmpty() ? $exact : Member::where(function ($q) use ($needle) {
                        $like = '%'.$needle.'%';
                        $q->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhereRaw("CONCAT(first_name,' ',last_name) like ?", [$like]);
                    })->orderBy('first_name')->limit(25)->get();

                    if ($candidates->isEmpty()) {
                        $fail = "No member matching '{$needle}'. Pass a member id, a full name, or use --username.";

                        return;
                    }
                    if ($candidates->count() > 1) {
                        $lines = $candidates
                            ->map(fn (Member $m) => "  #{$m->id}  ".trim($m->first_name.' '.$m->last_name))
                            ->implode(PHP_EOL);
                        $fail = "'{$needle}' matches {$candidates->count()} members. Re-run with --member=<id>:".PHP_EOL.$lines;

                        return;
                    }
                    $member = $candidates->first();
                }

                $leader = Leader::where('member_id', $member->id)->first();
                if (! $leader) {
                    // No login yet: mint one, same shape the admin app uses.
                    $issuedPassword = Str::ucfirst(Str::lower(Str::random(5))).random_int(1000, 9999);
                    $leader = Leader::create([
                        'member_id' => $member->id,
                        'username' => $this->uniqueUsername($member),
                        'password' => $issuedPassword, // hashed by the model mutator
                        'is_active' => true,
                    ]);
                } elseif (! $leader->is_active) {
                    $leader->update(['is_active' => true]);
                }
            } else {
                $fail = 'Say who to promote: --username=<login> or --member=<id or name> (or --list).';

                return;
            }

            if ($isSuper($leader)) {
                $result = [
                    'username' => $leader->username,
                    'name' => $leader->member ? trim($leader->member->first_name.' '.$leader->member->last_name) : $leader->username,
                    'password' => null,
                    'already' => true,
                ];

                return;
            }

            // Attach the role with no group. firstOrCreate + reactivate so a
            // previously removed (deactivated) role is reused, not duplicated.
            $leaderRole = LeaderRole::firstOrCreate(
                [
                    'leader_id' => $leader->id,
                    'role_definition_id' => $roleDef->id,
                    'group_id' => null,
                ],
                [
                    'assigned_at' => now(),
                    'is_active' => true,
                ],
            );
            if (! $leaderRole->is_active) {
                $leaderRole->update(['is_active' => true, 'assigned_at' => now()]);
            }

            $result = [
                'username' => $leader->username,
                'name' => $leader->member ? trim($leader->member->first_name.' '.$leader->member->last_name) : $leader->username,
                'password' => $issuedPassword,
                'already' => false,
            ];
        });

        if ($listOnly) {
            if (empty($supers)) {
                $this->warn('No super admins on this tenant yet.');

                return self::SUCCESS;
            }
            $this->table(['Username', 'Name', 'Active'],
                array_map(fn ($r) => [$r['username'], $r['name'], $r['active']], $supers));

            return self::SUCCESS;
        }

        if ($fail) {
            $this->error($fail);

            return self::FAILURE;
        }

        if (! $result) {
            return self::FAILURE;
        }

        $this->newLine();
        if ($result['already']) {
            $this->info("{$result['name']} ({$result['username']}) is already a super admin. Nothing to do.");

            return self::SUCCESS;
        }

        $this->info("Super admin granted to {$result['name']} — login: {$result['username']}");
        if ($result['password']) {
            $this->newLine();
            $this->line("  A new login was created. Password: {$result['password']}");
            $this->comment('Copy the password now — it is hashed on save and cannot be read back.');
        }
        $this->newLine();
        $this->comment('They can now appoint further super admins from inside the app.');

        return self::SUCCESS;
    }

    private function uniqueUsername(Member $member): string
    {
        $base = Str::slug(trim($member->first_name.' '.$member->last_name), '.');
        if ($base === '') {
            $base = 'leader';
        }

        $username = $base;
        $n = 1;
        while (Leader::where('username', $username)->exists()) {
            $n++;
            $username = $base.$n;
        }

        return $username;
    }
}
