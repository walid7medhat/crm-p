<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAnnouncementNotifications extends Command
{
    protected $signature = 'announcements:send-notifications {--date= : Specific date (Y-m-d)}';
    protected $description = 'Send notifications for announcements starting today';

    public function handle()
    {
        $date = $this->option('date') ?: now()->toDateString();
        
        $this->info("Sending notifications for announcements starting on: {$date}");
        
        $announcements = Announcement::whereDate('start_date', $date)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->get();
        
        if ($announcements->isEmpty()) {
            $this->info("No announcements found for today.");
            return 0;
        }
        
        $totalSent = 0;
        
        foreach ($announcements as $announcement) {
            $this->info("Processing: {$announcement->title}");
            
            $users = $announcement->getTargetUsers();
            
            $count = 0;
            foreach ($users as $user) {
                try {
                    $user->notify(new AnnouncementNotification($announcement));
                    $count++;
                } catch (\Exception $e) {
                    Log::error("Failed to send notification to user {$user->id}: {$e->getMessage()}");
                }
            }
            
            $totalSent += $count;
            $this->info("  Sent to {$count} users");
        }
        
        $this->info("Done! Total notifications sent: {$totalSent}");
        
        return 0;
    }
}