<?php

namespace App\Mail;

use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicantStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $applicant;
    public $status;

    public function __construct(Applicant $applicant, $status)
    {
        $this->applicant = $applicant;
        $this->status = $status;
    }

    public function build()
    {
        $subject = match($this->status) {
            'shortlisted' => 'Good News! You have been shortlisted',
            'rejected' => 'Update on your application',
            'hired' => 'Congratulations! Job Offer',
            default => 'Application Status Update'
        };
        
        return $this->subject($subject)
                    ->view('emails.applicant-status')
                    ->with([
                        'applicant' => $this->applicant,
                        'status' => $this->status,
                    ]);
    }
}