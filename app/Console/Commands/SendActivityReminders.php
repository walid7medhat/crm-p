<?php

namespace App\Console\Commands;

use App\Models\LeadActivity;
use App\Models\User;
use App\Notifications\ActivityReminderNotification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendActivityReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'activities:send-reminders 
                            {--timeframe=upcoming : Timeframe for reminders (upcoming, today, tomorrow, overdue)} 
                            {--test : Send test notifications}';

    /**
     * The console command description.
     */
    protected $description = 'Send reminders for upcoming activities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeframe = $this->option('timeframe');
        $isTest = $this->option('test');
        
        $this->info("Starting activity reminders for: {$timeframe}");
        
        switch ($timeframe) {
            case 'today':
                $activities = $this->getTodayActivities();
                break;
            case 'tomorrow':
                $activities = $this->getTomorrowActivities();
                break;
            case 'overdue':
                $activities = $this->getOverdueActivities();
                break;
            default:
                $activities = $this->getUpcomingActivities();
                break;
        }
        
        $count = $activities->count();
        
        if ($count === 0) {
            $this->info("No activities found for {$timeframe} timeframe.");
            return;
        }
        
        $this->info("Found {$count} activities for {$timeframe} timeframe.");
        
        if ($isTest) {
            $this->sendTestNotifications($activities);
        } else {
            $this->sendRealNotifications($activities, $timeframe);
        }
        
        $this->info("Activity reminders sent successfully!");
    }
    
    /**
     * Get activities happening today
     */
    private function getTodayActivities()
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        
        return LeadActivity::with(['user', 'lead'])
            ->whereBetween('reminder_date', [$todayStart, $todayEnd])
            ->where('is_completed', false)
            ->get();
    }
    
    /**
     * Get activities happening tomorrow
     */
    private function getTomorrowActivities()
    {
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();
        
        return LeadActivity::with(['user', 'lead'])
            ->whereBetween('reminder_date', [$tomorrowStart, $tomorrowEnd])
            ->where('is_completed', false)
            ->get();
    }
    
    /**
     * Get overdue activities
     */
    private function getOverdueActivities()
    {
        return LeadActivity::with(['user', 'lead'])
            ->where('reminder_date', '<', Carbon::now())
            ->where('is_completed', false)
            ->get();
    }
    
    /**
     * Get upcoming activities (next 24 hours)
     */
    private function getUpcomingActivities()
    {
        $now = Carbon::now();
        $next24Hours = Carbon::now()->addHours(24);
        
        return LeadActivity::with(['user', 'lead'])
            ->whereBetween('reminder_date', [$now, $next24Hours])
            ->where('is_completed', false)
            ->get();
    }
    
    /**
     * Send test notifications
     */
    private function sendTestNotifications($activities)
    {
        $this->info("SENDING TEST NOTIFICATIONS...");
        
        foreach ($activities as $activity) {
            $this->info("Test Reminder: Activity '{$activity->title}' for user {$activity->user->name}");
            $this->info("Lead: {$activity->lead->name} - Time: " . $activity->reminder_date->format('Y-m-d H:i'));
            $this->info("---");
        }
    }
    
    /**
     * Send real notifications
     */
    private function sendRealNotifications($activities, $timeframe)
    {
        $sentCount = 0;
        
        foreach ($activities as $activity) {
            try {
                // Send notification to the user assigned to the activity
                $activity->user->notify(new ActivityReminderNotification($activity, $timeframe));
                
                // Also notify the lead owner if different from activity user
                if ($activity->lead->responsible_person_id && 
                    $activity->lead->responsible_person_id != $activity->user_id) {
                    
                    $leadOwner = User::find($activity->lead->responsible_person_id);
                    if ($leadOwner) {
                        $leadOwner->notify(new ActivityReminderNotification($activity, $timeframe, true));
                    }
                }
                
                $sentCount++;
                
                $this->info("✅ Sent reminder for: '{$activity->title}' to {$activity->user->name}");
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to send reminder for activity ID {$activity->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Total notifications sent: {$sentCount}");
    }
}