<?php

namespace App\Console\Commands;

use App\Events\LeadReverted;
use Illuminate\Console\Command;
use App\Models\Lead;
use App\Events\LeadUpdated;
use App\Notifications\LeadRevertWarningNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class CheckLeadRevert extends Command
{
    protected $signature = 'leads:check-revert';
    protected $description = 'Check and revert leads that stayed in stage 2 for more than 1 hour';

    public function handle(): void
    {
        $this->info('🔄 Starting leads:check-revert at ' . now()->toDateTimeString());
        Log::info('🔄 Starting leads:check-revert', ['time' => now()->toDateTimeString()]);
        
        // 🔄 Revert
        $this->revertLeads();

        // 🔔 Notification
        $this->sendNotifications();
        
        $this->info('✅ Finished leads:check-revert');
        Log::info('✅ Finished leads:check-revert');
    }

    private function revertLeads(): void
    {
        $leads = Lead::with(['stage.revertToStage'])
            ->whereHas('stage', function ($q) {
                $q->where('auto_revert', true);
            })
            ->whereNotNull('last_stage_change_at')
            ->get();

        $this->info("📊 Found " . $leads->count() . " leads to check for revert");
        Log::info("📊 Leads to check for revert", ['count' => $leads->count()]);

        foreach ($leads as $lead) {
            if ($lead->shouldAutoRevert()) {
                $lead->revertToPreviousStage();
                $this->info("✅ Lead {$lead->id} reverted to stage: " . ($lead->stage?->name ?? 'unknown'));
                Log::info("✅ Lead reverted", ['lead_id' => $lead->id, 'stage' => $lead->stage?->name]);
            }
        }
    }

    private function sendNotifications(): void
    {
        $leads = Lead::with(['stage.revertToStage', 'responsiblePerson', 'observingUsers'])
            ->whereHas('stage', function ($q) {
                $q->where('auto_revert', true);
            })
            ->whereNotNull('last_stage_change_at')
            ->get();

        $this->info("📊 Found " . $leads->count() . " leads to check for notifications");
        Log::info("📊 Leads to check for notifications", ['count' => $leads->count()]);

        foreach ($leads as $lead) {
            $stage = $lead->stage;

            if (!$stage || !$stage->auto_revert) {
                $this->warn("⚠️ Lead {$lead->id} - No stage or auto_revert disabled");
                continue;
            }

            // ✅ Log lead details
            $this->line("🔍 Processing Lead ID: {$lead->id}");
            $this->line("   └─ Stage: {$stage->name} (ID: {$stage->id})");
            $this->line("   └─ last_stage_change_at: " . ($lead->last_stage_change_at?->toDateTimeString() ?? 'null'));
            $this->line("   └─ revert_after_hours: {$stage->revert_after_hours}");
            
            // ✅ حساب وقت الرجوع
            if ($lead->last_stage_change_at && $stage->revert_after_hours) {
                $revertTime = $lead->last_stage_change_at->copy()->addHours($stage->revert_after_hours);
                $this->line("   └─ Revert time: {$revertTime->toDateTimeString()}");
                $this->line("   └─ Time remaining: " . now()->diffForHumans($revertTime));
            }

            // الحصول على أوقات الإشعارات من المرحلة
            $notificationTimes = $stage->notification_times ?? [30];
            
            // ✅ تأكد من أن $notificationTimes مصفوفة
            if (!is_array($notificationTimes)) {
                $notificationTimes = [30];
            }
            
            // ✅ Log notification times
            $this->line("   └─ Notification times: " . implode(', ', $notificationTimes));
            Log::info("🔔 Lead notification check", [
                'lead_id' => $lead->id,
                'notification_times' => $notificationTimes,
                'sent_times' => $lead->notification_times_sent ?? []
            ]);

            foreach ($notificationTimes as $minutesBefore) {
                // ✅ Log each check
                $this->line("      └─ Checking {$minutesBefore} minutes before revert...");
                
                // ✅ حساب وقت الإشعار
                if ($lead->last_stage_change_at && $stage->revert_after_hours) {
                    $revertTime = $lead->last_stage_change_at->copy()->addHours($stage->revert_after_hours);
                    $notifyTime = $revertTime->copy()->subMinutes($minutesBefore);
                    $this->line("         └─ Notify at: {$notifyTime->toDateTimeString()}");
                    $this->line("         └─ Current time: " . now()->toDateTimeString());
                    
                    // ✅ التحقق من الوقت
                    $diffInSeconds = now()->diffInSeconds($notifyTime);
                    $this->line("         └─ Difference: {$diffInSeconds} seconds");
                }
                
                if ($lead->shouldSendRevertNotificationAt($minutesBefore)) {
                    $targetStage = $lead->getRevertTargetStage();

                    $this->info("📨 Lead {$lead->id} - Sending notification for {$minutesBefore} minutes before revert");
                    Log::info("📨 Sending notification", [
                        'lead_id' => $lead->id,
                        'minutes_before' => $minutesBefore,
                        'target_stage' => $targetStage?->name
                    ]);

                    // الحصول على الرسالة المخصصة أو رسالة افتراضية
                    $message = $stage->revert_notification_message 
                        ?: "⚠️ Lead will be reverted to {$targetStage?->name} in {$minutesBefore} minutes";

                    $users = collect([$lead->responsiblePerson])
                        ->merge($lead->observingUsers)
                        ->filter()
                        ->unique('id');

                    if ($users->isNotEmpty()) {
                        Notification::send(
                            $users,
                            new LeadRevertWarningNotification(
                                $lead,
                                $targetStage?->name ?? 'previous stage',
                                $minutesBefore,
                                $message 
                            )
                        );
                        try {
                            broadcast(new LeadReverted(
                                $lead,
                                $minutesBefore,
                                $targetStage?->name ?? 'previous stage',
                                $stage->revert_notification_message ?? null
                            ));
                            
                            $this->info("📡 Revert event broadcasted for Lead {$lead->id}");
                        } catch (\Exception $e) {
                            $this->error("❌ Failed to broadcast revert event: " . $e->getMessage());
                        }
                        // ✅ تسجيل إرسال الإشعار
                        $lead->markNotificationSent($minutesBefore);

                        $this->info("✅ Lead {$lead->id} notified: {$minutesBefore} minutes before revert");
                        $this->info("   └─ Users: " . $users->pluck('name')->implode(', '));
                        Log::info("✅ Notification sent", [
                            'lead_id' => $lead->id,
                            'minutes_before' => $minutesBefore,
                            'users' => $users->pluck('id')->toArray()
                        ]);
                    } else {
                        $this->warn("⚠️ Lead {$lead->id} - No users to notify");
                        Log::warning("⚠️ No users to notify", ['lead_id' => $lead->id]);
                    }
                } else {
                    // ✅ Log why notification was not sent
                    $sentTimes = $lead->notification_times_sent ?? [];
                    if (in_array($minutesBefore, $sentTimes)) {
                        $this->line("         └─ Already sent for {$minutesBefore} minutes");
                    } else {
                        $this->line("         └─ Not time yet or conditions not met");
                    }
                }
            }
        }
    }
}