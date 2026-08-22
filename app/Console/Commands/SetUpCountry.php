<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Stand one country up end to end.
 *
 * `flock:add-country-tier` gets as far as a country with its churches
 * underneath, which is not yet a working country. This finishes the job:
 *
 *   1. A stream under every gathering service, carrying the same name. That is
 *      the client's shape, and bacentas and governors hang off the stream
 *      later.
 *   2. A panel admin confined to the country.
 *   3. A leader per church, but only once a role exists that applies to them.
 *      Nobody in this tenant leads a gathering service, so in a fresh country
 *      this step is skipped and reported rather than guessed at.
 *
 * Three different things get created and they are easy to confuse:
 *
 *   User        an email/password login for the Filament admin panel, scoped
 *               by scope_group_id.
 *   Member      a person on the roll. A Leader cannot exist without one,
 *               because a leader is a member who has been given a login.
 *   Leader      a username/password login for the mobile app, plus a
 *               LeaderRole saying which group they lead.
 *
 * Idempotent. Anything already there is left exactly as it is and reported as
 * "exists", so a half-finished country can be finished by running it again.
 * Existing passwords are never reset, because that would lock out whoever is
 * already using the account.
 *
 * Run --dry first. It writes nothing and prints precisely what it would do.
 */
class SetUpCountry extends Command
{
    protected $signature = 'flock:setup-country
        {tenant : Tenant id, e.g. go-church}
        {country : Country group name, e.g. Switzerland}
        {--admin-email= : Panel login. Defaults to <country>@<tenant>.flock}
        {--leader-role= : Role definition slug for church leaders, if it cannot be inferred}
        {--leader-domain=flock.local : Domain used to build each leader username}
        {--dry : Show what would happen and write nothing}';

    protected $description = 'Create the panel admin and one leader per church for a country';

    /** Credentials are printed once at the end and stored nowhere readable. */
    protected array $issued = [];

    /** Groups created. Counted separately: they carry no credential, and a
        summary that only counted logins announced "nothing to create" while
        listing eight streams it was about to make. */
    protected int $groupsMade = 0;

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error("No tenant with id {$this->argument('tenant')}.");

            return self::FAILURE;
        }

        $code = self::SUCCESS;
        $tenant->run(function () use (&$code) {
            $code = $this->setUp((bool) $this->option('dry'));
        });

        return $code;
    }

    /* protected, not private, so a test can drive it against a tenant
       database directly, the same way AddCountryTier is tested. */
    protected function setUp(bool $dry): int
    {
        $countryName = $this->argument('country');

        $country = Group::where('name', $countryName)->first();
        if (! $country) {
            $this->error("No group named \"{$countryName}\".");
            $this->line('  Run flock:add-country-tier first, which creates the countries.');

            return self::FAILURE;
        }

        $churches = Group::where('parent_id', $country->id)->orderBy('name')->get();
        if ($churches->isEmpty()) {
            $this->error("\"{$countryName}\" has no churches under it.");

            return self::FAILURE;
        }

        $this->line($dry ? 'DRY RUN — nothing will be written.' : 'Applying changes.');
        $this->line("  country:     {$country->name} ({$churches->count()} churches)");
        $this->newLine();

        $streamType = GroupType::where('slug', 'stream')->first();
        if (! $streamType) {
            $this->error('There is no "stream" group type in this tenant.');

            return self::FAILURE;
        }

        foreach ($churches as $church) {
            $this->makeStream($church, $streamType, $dry);
        }

        $this->newLine();
        $this->makeAdmin($country, $dry);

        /*
         * Leaders are last and optional.
         *
         * Nobody in this tenant leads a gathering service: every leader sits on
         * a bacenta, a basonta or a governor, all of which are below the church
         * and do not exist yet in a new country. So when no role applies, the
         * structure and the admin are still built and the people are skipped,
         * rather than the whole run failing and leaving the country empty.
         */
        $role = $this->resolveLeaderRole($churches->first(), quiet: ! $this->option('leader-role'));

        if ($role) {
            $this->newLine();
            $this->line("  leader role: {$role->slug}");
            foreach ($churches as $church) {
                $this->makeLeader($church, $role, $dry);
            }
        } else {
            $this->newLine();
            $this->comment('  No leaders created: no role applies to these churches yet.');
            $this->line('  Add the bacentas and governors, then re-run with --leader-role=<slug>.');
        }

        $this->report($dry);

        return self::SUCCESS;
    }

    /**
     * Every gathering service gets a stream of the same name beneath it.
     *
     * That is the client's own shape: the church is the gathering service, and
     * the stream directly under it carries the same name. Bacentas and
     * governors hang off the stream later.
     */
    protected function makeStream(Group $church, GroupType $streamType, bool $dry): void
    {
        $existing = Group::where('parent_id', $church->id)
            ->where('group_type_id', $streamType->id)
            ->where('name', $church->name)
            ->first();

        if ($existing) {
            $this->line("  = {$church->name}: stream exists");

            return;
        }

        $this->line("  + {$church->name}: stream \"{$church->name}\"");
        $this->groupsMade++;

        if (! $dry) {
            Group::create([
                'name' => $church->name,
                'group_type_id' => $streamType->id,
                'parent_id' => $church->id,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Which role a church leader should get.
     *
     * Never hard-coded to a slug: role definitions are tenant-defined, so the
     * names differ between tenants and guessing one silently creates leaders
     * with the wrong authority. Prefer a role declared to apply to the
     * churches' own group type, and if that is ambiguous, say so rather than
     * picking.
     */
    protected function resolveLeaderRole(Group $church, bool $quiet = false): ?RoleDefinition
    {
        if ($slug = $this->option('leader-role')) {
            $role = RoleDefinition::where('slug', $slug)->first();
            if (! $role) {
                $this->error("No role definition with slug \"{$slug}\".");
                $this->line('  Available: '.RoleDefinition::pluck('slug')->implode(', '));
            }

            return $role;
        }

        $matching = RoleDefinition::where('applies_to_group_type_id', $church->group_type_id)
            ->orderByDesc('permission_level')
            ->get();

        if ($matching->count() === 1) {
            return $matching->first();
        }

        if ($quiet) {
            return null;
        }

        $type = GroupType::find($church->group_type_id);
        $label = $type->slug ?? 'that type';

        if ($matching->isEmpty()) {
            $this->error("No role definition applies to group type \"{$label}\".");
        } else {
            $this->error("Several roles apply to \"{$label}\": ".$matching->pluck('slug')->implode(', '));
        }
        $this->line('  Pass --leader-role=<slug> to say which one church leaders get.');
        $this->line('  Available: '.RoleDefinition::pluck('slug')->implode(', '));

        return null;
    }

    /** The Filament login, confined to this country. */
    protected function makeAdmin(Group $country, bool $dry): void
    {
        $email = strtolower(
            $this->option('admin-email')
            ?: Str::slug($country->name).'@'.$this->argument('tenant').'.flock'
        );

        if (User::where('email', $email)->exists()) {
            $this->line("  = admin {$email} exists, left alone");

            return;
        }

        $password = Str::password(14, true, true, false);
        $this->line("  + admin {$email}");

        if (! $dry) {
            User::create([
                'name' => $country->name.' Admin',
                'email' => $email,
                'password' => $password,
                'scope_group_id' => $country->id,
            ]);
        }

        $this->issued[] = ['panel admin', $email, $password];
    }

    /**
     * One leader per church: a Member to be the person, a Leader to be the
     * login, and a LeaderRole to say what they lead.
     */
    protected function makeLeader(Group $church, RoleDefinition $role, bool $dry): void
    {
        /* Defaulted here as well as in the signature. Relying on the signature
           alone means anything driving the command other than the console, a
           test or a Schedule::command, builds usernames ending in a bare "@". */
        $domain = $this->option('leader-domain') ?: 'flock.local';
        $username = Str::slug($church->name).'@'.$domain;

        $existing = Leader::where('username', $username)->first();
        if ($existing) {
            /* The login is there. The role might not be, if a previous run
               died between the two, so check it separately rather than
               assuming the pair. */
            $hasRole = LeaderRole::where('leader_id', $existing->id)
                ->where('group_id', $church->id)
                ->exists();

            if ($hasRole) {
                $this->line("  = {$church->name}: {$username} exists, left alone");

                return;
            }

            $this->line("  ~ {$church->name}: {$username} exists but leads nothing, adding role");
            if (! $dry) {
                $this->attachRole($existing, $church, $role);
            }

            return;
        }

        $password = Str::password(12, true, true, false);
        $this->line("  + {$church->name}: {$username}");

        if (! $dry) {
            $member = Member::create([
                'first_name' => $church->name,
                'last_name' => 'Leader',
                'email' => $username,
                'member_type' => 'registered',
                'is_active' => true,
                'notes' => 'Created by flock:setup-country for '.$church->name.'.',
            ]);

            $leader = Leader::create([
                'member_id' => $member->id,
                'username' => $username,
                'password' => $password,
                'is_active' => true,
            ]);

            $this->attachRole($leader, $church, $role);
        }

        $this->issued[] = [$church->name, $username, $password];
    }

    protected function attachRole(Leader $leader, Group $church, RoleDefinition $role): void
    {
        LeaderRole::create([
            'leader_id' => $leader->id,
            'role_definition_id' => $role->id,
            'group_id' => $church->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);
    }

    protected function report(bool $dry): void
    {
        $this->newLine();

        if (! $this->issued && ! $this->groupsMade) {
            $this->info('Nothing to do. Everything was already there.');

            return;
        }

        $parts = [];
        if ($this->groupsMade) {
            $parts[] = $this->groupsMade.' group(s)';
        }
        if ($this->issued) {
            $parts[] = count($this->issued).' account(s)';
        }
        $summary = implode(' and ', $parts);

        if ($dry) {
            $this->info($summary.' would be created. Re-run without --dry.');

            return;
        }

        $this->info('Created '.$summary.'.');

        if (! $this->issued) {
            return;
        }

        $this->newLine();
        $this->table(['For', 'Username', 'Password'], $this->issued);
        $this->newLine();
        $this->comment('  Copy these now. The passwords are hashed on save and cannot be read back.');
    }
}
