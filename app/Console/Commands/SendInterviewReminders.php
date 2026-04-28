<?php

namespace App\Console\Commands;

use App\Models\Interview;
use App\Mail\InterviewReminderMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;

class SendInterviewReminders extends Command
{
    protected $signature = 'interviews:send-reminders {--hours=24 : Send reminders for interviews within X hours}';
    protected $description = 'Send interview reminders to interviewers';

    public function handle()
    {
        $hours = (int) $this->option('hours');
        $reminderTime = now()->addHours($hours);
        
        $interviews = Interview::with(['applicant', 'interviewer', 'job'])
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), $reminderTime])
            ->get();
        
        foreach ($interviews as $interview) {
            // Send to interviewer (manager)
            Mail::to($interview->interviewer->email)
                ->send(new InterviewReminderMail($interview, $interview->applicant, 'reminder'));
            
            $this->info("Reminder sent to: {$interview->interviewer->email}");
        }
        
        $this->info("Sent " . $interviews->count() . " reminders");
    }
}