<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turn the public camp registration page on (or off) for the tenant that owns
 * a given domain, and pin it to that domain.
 *
 * The camp (Living Waters & First Love Camp) is a Eurozone event, served on
 * fl-eurozone.church-stack.com. That domain belongs to the shared go-church
 * tenant, which also answers on gochurch.church-stack.com. Passing the domain
 * here does double duty: it finds the right tenant AND becomes the camp_domain
 * host guard, so enabling the feature exposes /camp only on the Eurozone
 * address and never on the tenant's other domain.
 *
 *   php artisan camp:enable fl-eurozone.church-stack.com            # go live
 *   php artisan camp:enable fl-eurozone.church-stack.com --close    # close registration
 *   php artisan camp:enable fl-eurozone.church-stack.com --open     # reopen
 *   php artisan camp:enable fl-eurozone.church-stack.com --off      # hide the page again
 *
 * Idempotent: re-running changes only what the flags say and reports the
 * resulting state. Warns (does not fail) if the camp_registrations table is
 * missing on the tenant, which means `tenants:migrate` still needs running.
 */
class CampEnable extends Command
{
    protected $signature = 'camp:enable
        {domain : the host the camp should serve on, e.g. fl-eurozone.church-stack.com}
        {--off : hide the camp page on this tenant instead of enabling it}
        {--close : close registration (page stays visible, submissions refused)}
        {--open : reopen registration}';

    protected $description = 'Enable/disable the public camp registration page for the tenant that owns a domain';

    public function handle(): int
    {
        $domain = $this->argument('domain');

        if ($this->option('close') && $this->option('open')) {
            $this->error('Pass either --close or --open, not both.');

            return self::FAILURE;
        }

        $tenantId = DB::table('domains')->where('domain', $domain)->value('tenant_id');
        if (! $tenantId) {
            $this->error("No tenant is claimed for domain '{$domain}'. Check `php artisan tenants:list`.");

            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant {$tenantId} not found.");

            return self::FAILURE;
        }

        $off = (bool) $this->option('off');
        $close = (bool) $this->option('close');
        $open = (bool) $this->option('open');

        $state = [];
        $tableMissing = false;

        $tenant->run(function () use ($domain, $off, $close, $open, &$state, &$tableMissing) {
            if ($off) {
                Setting::set('camp_enabled', false, 'boolean');
            } else {
                Setting::set('camp_enabled', true, 'boolean');
                Setting::set('camp_domain', $domain, 'string');
                if ($close) {
                    Setting::set('camp_registrations_closed', true, 'boolean');
                }
                if ($open) {
                    Setting::set('camp_registrations_closed', false, 'boolean');
                }
            }

            $state = [
                'enabled' => (bool) Setting::get('camp_enabled', false),
                'domain' => Setting::get('camp_domain'),
                'closed' => (bool) Setting::get('camp_registrations_closed', false),
            ];
            $tableMissing = ! Schema::hasTable('camp_registrations');
        });

        $this->newLine();
        $this->info("Tenant: {$tenant->getTenantKey()}  (domain {$domain})");
        $this->line('  camp_enabled: '.($state['enabled'] ? 'true' : 'false'));
        $this->line('  camp_domain:  '.($state['domain'] ?: '(all domains)'));
        $this->line('  registration: '.($state['closed'] ? 'CLOSED' : 'open'));

        if ($state['enabled'] && $tableMissing) {
            $this->newLine();
            $this->warn('The camp_registrations table does not exist on this tenant yet.');
            $this->warn("Run:  php artisan tenants:migrate --tenants={$tenant->getTenantKey()} --force");
        }

        $this->newLine();
        if ($state['enabled']) {
            $this->comment("Live at: https://{$domain}/camp");
        } else {
            $this->comment('The camp page is now hidden on this tenant (404).');
        }

        return self::SUCCESS;
    }
}
