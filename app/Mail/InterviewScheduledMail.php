<?php

namespace App\Mail;

use App\Models\Interview;
use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $interview;
    public $applicant;

    public function __construct(Interview $interview, Applicant $applicant)
    {
        $this->interview = $interview;
        $this->applicant = $applicant;
    }

    public function build()
    {
        return $this->subject('Interview Scheduled - ' . $this->applicant->job->title)
                    ->view('emails.interview-scheduled')
                    ->with([
                        'interview' => $this->interview,
                        'applicant' => $this->applicant,
                    ]);
    }
}