<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AttendanceReminderService;
use Illuminate\Console\Command;

class SendAttendanceCounterReminders extends Command
{
    protected $signature = 'attendance-counter:remind';

    protected $description = 'Send a running head-count summary to role holders every 30 min while a service window is open';

    public function handle(AttendanceReminderService $service): int
    {
        // Already inside a tenant: just process it.
        if (tenant()) {
            $results = $service->sendDueReminders();
            $this->info("Attendance summaries - sent: {$results['sent']}, skipped: {$results['skipped']}");

            return self::SUCCESS;
        }

        // Central context: fan out over every active tenant.
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($service, $tenant) {
                $results = $service->sendDueReminders();
                if ($results['sent'] > 0 || $results['skipped'] > 0) {
                    $this->info("[{$tenant->church_name}] sent: {$results['sent']}, skipped: {$results['skipped']}");
                }
            });
        }

        return self::SUCCESS;
    }
}
