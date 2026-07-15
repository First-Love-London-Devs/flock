<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Runs hourly; the command itself fires per tenant only at 07:00 in that
        // church's own local timezone (see SendBirthdayReminders).
        $schedule->command('birthdays:send')->hourly();
        $schedule->command('attendance:check-completion')->everyThirtyMinutes();
        $schedule->command('attendance-counter:remind')->everyThirtyMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
