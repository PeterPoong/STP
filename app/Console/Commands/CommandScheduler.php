<?php

namespace App\Console\Commands;

use Illuminate\Console\Scheduling\Schedule;

class CommandScheduler
{
    /**
     * Register scheduled tasks.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command('app:send-interested-course-category-email-cron')->daily();
        $schedule->command('app:send-interested-course-category-email-cron')->everyTwoMinutes();
    }
}
