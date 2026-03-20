<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BirthdayNotificationService;
use Illuminate\Console\Command;

class SendBirthdayReminders extends Command
{
    protected $signature = 'birthdays:send';
    protected $description = 'Send birthday reminder notifications to leaders';

    public function handle(BirthdayNotificationService $service): int
    {
        // If running in tenant context, process directly
        if (tenant()) {
            $results = $service->processBirthdayNotifications();
            $this->info("Birthday reminders sent - Today: {$results['today']}, Tomorrow: {$results['tomorrow']}, Next week: {$results['one_week']}");
            return self::SUCCESS;
        }

        // If running from central, iterate all tenants
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($service, $tenant) {
                $results = $service->processBirthdayNotifications();
                $this->info("[{$tenant->church_name}] Today: {$results['today']}, Tomorrow: {$results['tomorrow']}, Next week: {$results['one_week']}");
            });
        }

        return self::SUCCESS;
    }
}
