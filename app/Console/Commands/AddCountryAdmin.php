<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Create an admin panel login confined to one country.
 *
 * The panel account and the mobile app account are different things: this
 * makes a User (email + password, Filament), not a Leader. Leaders are
 * scoped by the group their role sits on; panel users are scoped by
 * scope_group_id, which is what this sets.
 *
 * Leave --group off and you get a group-wide admin who sees all forty
 * churches, which is what every existing login already is.
 */
class AddCountryAdmin extends Command
{
    protected $signature = 'flock:add-country-admin
        {tenant : Tenant id, e.g. go-church}
        {email : Login email for the new admin}
        {--group= : Group name to confine them to, e.g. Belgium. Omit for group-wide}
        {--name= : Display name, defaults to the group name}
        {--password= : Defaults to a generated one, printed once}';

    protected $description = 'Create a Filament admin login, optionally confined to one country';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error("No tenant with id {$this->argument('tenant')}.");

            return self::FAILURE;
        }

        return $tenant->run(function () {
            $email = strtolower(trim($this->argument('email')));

            if (User::where('email', $email)->exists()) {
                $this->error("{$email} already has a login on this tenant.");

                return self::FAILURE;
            }

            $group = null;
            if ($name = $this->option('group')) {
                $group = Group::where('name', $name)->first();
                if (! $group) {
                    $this->error("No group named \"{$name}\" in this tenant.");

                    return self::FAILURE;
                }
            }

            /* Printed once and never stored anywhere readable, so it has to
               be handed over deliberately rather than looked up later. */
            $password = $this->option('password') ?: Str::password(14, true, true, false);

            User::create([
                'name' => $this->option('name') ?: ($group->name ?? 'Group').' Admin',
                'email' => $email,
                'password' => $password,
                'scope_group_id' => $group?->id,
            ]);

            $this->newLine();
            $this->info('Admin created.');
            $this->line("  email:    {$email}");
            $this->line("  password: {$password}");

            if ($group) {
                $this->line('  sees:     '.$group->name.' and everything under it ('
                    .$group->allGroupIds()->count().' groups)');
            } else {
                $this->warn('  sees:     the WHOLE tenant, every country. No --group was given.');
            }

            $this->newLine();
            $this->comment('  Write the password down now. It is not recoverable.');

            return self::SUCCESS;
        });
    }
}
