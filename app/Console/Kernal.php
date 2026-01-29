<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // ==================== SET TIMEZONE FOR ALL SCHEDULES ====================
        $schedule->timezone('Asia/Dubai');
        
        // ==================== LEADS REVERT COMMAND ====================
        $schedule->command('leads:check-revert')
            ->everyTwoHours()
            ->description('Check for leads that need to be reverted every 2 hours (UAE Time)');
        
        // ==================== ACTIVITY REMINDERS ====================
        // Send reminders every 2 hours for upcoming activities
        $schedule->command('activities:send-reminders --timeframe=upcoming')
            ->everyTwoHours()
            ->description('Send reminders for activities in next 24 hours (every 2 hours, UAE Time)');
            
        // Send morning reminders at 9 AM
        $schedule->command('activities:send-reminders --timeframe=today')
            ->dailyAt('09:00')
            ->description('Send morning reminders at 9:00 AM (UAE Time)');
            
        // Send afternoon reminders at 2 PM
        $schedule->command('activities:send-reminders --timeframe=today')
            ->dailyAt('14:00')
            ->description('Send afternoon reminders at 2:00 PM (UAE Time)');
            
        // Send reminders at 5 PM for tomorrow's activities
        $schedule->command('activities:send-reminders --timeframe=tomorrow')
            ->dailyAt('17:00')
            ->description('Send reminders for tomorrow\'s activities at 5:00 PM (UAE Time)');
            
        // Send overdue reminders every 6 hours
        $schedule->command('activities:send-reminders --timeframe=overdue')
            ->everySixHours()
            ->description('Send reminders for overdue activities (UAE Time)');
            
        // ==================== TEST COMMANDS ====================
        $schedule->command('activities:send-reminders --timeframe=today --test')
            ->dailyAt('10:00')
            ->description('Test activity reminders at 10:00 AM (UAE Time)');
            
        // Test leads command
        $schedule->command('leads:check-revert --test')
            ->dailyAt('11:00')
            ->description('Test leads revert at 11:00 AM (UAE Time)');
            
        // ==================== CLEANUP COMMANDS ====================
        // Clean old notifications weekly
        // $schedule->command('notifications:cleanup')
        //     ->weekly()
        //     ->sundays()
        //     ->at('01:00')
        //     ->description('Clean old notifications every Sunday at 1:00 AM (UAE Time)');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}