<?php

namespace App\Mail;

use App\Models\Interview;
use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $interview;
    public $applicant;
    public $type;

    public function __construct(Interview $interview, Applicant $applicant, $type = 'scheduled')
    {
        $this->interview = $interview;
        $this->applicant = $applicant;
        $this->type = $type;
    }

    public function build()
    {
        $subject = $this->type == 'reminder' 
            ? 'Reminder: Upcoming Interview Today'
            : 'Interview Scheduled - Action Required';
            
        return $this->subject($subject)
                    ->view('emails.interview-reminder')
                    ->with([
                        'interview' => $this->interview,
                        'applicant' => $this->applicant,
                    ]);
    }
}