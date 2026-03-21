<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class MigrateDomains extends Command
{
    protected $signature = 'tenant:migrate-domains {old} {new}';
    protected $description = 'Replace domain suffix for all tenants (e.g. poimen.co.uk -> church-stack.com)';

    public function handle(): int
    {
        $old = $this->argument('old');
        $new = $this->argument('new');

        $domains = Domain::all();

        if ($domains->isEmpty()) {
            $this->warn('No domains found.');
            return self::SUCCESS;
        }

        $this->info("Current domains:");
        foreach ($domains as $domain) {
            $this->line("  - {$domain->domain} (tenant: {$domain->tenant_id})");
        }

        $updated = 0;
        foreach ($domains as $domain) {
            if (str_contains($domain->domain, $old)) {
                $newDomain = str_replace($old, $new, $domain->domain);
                $domain->update(['domain' => $newDomain]);
                $this->info("  Updated: {$domain->getOriginal('domain')} -> {$newDomain}");
                $updated++;
            }
        }

        if ($updated === 0) {
            $this->warn("No domains contained '{$old}'. Nothing to update.");
        } else {
            $this->info("{$updated} domain(s) updated.");
        }

        $this->info("\nDomains after migration:");
        foreach (Domain::all() as $domain) {
            $this->line("  - {$domain->domain} (tenant: {$domain->tenant_id})");
        }

        return self::SUCCESS;
    }
}
