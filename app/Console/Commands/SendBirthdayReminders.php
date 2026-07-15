<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BirthdayNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBirthdayReminders extends Command
{
    /** Send birthday reminders at this hour of each church's local time. */
    private const SEND_HOUR = 7;

    protected $signature = 'birthdays:send {--force : Send now regardless of the local hour}';

    protected $description = 'Send birthday reminder notifications to leaders (at 07:00 in each church\'s local timezone)';

    public function handle(BirthdayNotificationService $service): int
    {
        // If running in tenant context, process directly (manual/testing).
        if (tenant()) {
            $results = $service->processBirthdayNotifications();
            $this->info("Birthday reminders sent - Today: {$results['today']}, Tomorrow: {$results['tomorrow']}, Next week: {$results['one_week']}");

            return self::SUCCESS;
        }

        // Scheduled hourly from central: for each tenant, only fire when it is
        // 07:00 in that church's own local timezone. The service dedupes per
        // member/leader/day, so this sends once per day even if run repeatedly.
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($service, $tenant) {
                if (! $this->option('force') && Carbon::now($tenant->getTimezone())->hour !== self::SEND_HOUR) {
                    return;
                }

                $results = $service->processBirthdayNotifications();
                $this->info("[{$tenant->church_name}] Today: {$results['today']}, Tomorrow: {$results['tomorrow']}, Next week: {$results['one_week']}");
            });
        }

        return self::SUCCESS;
    }
}
