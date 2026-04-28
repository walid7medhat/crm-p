<?php

namespace App\Mail;

use App\Models\Job;
use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $job;
    public $applicant;

    public function __construct(Job $job, Applicant $applicant)
    {
        $this->job = $job;
        $this->applicant = $applicant;
    }

    public function build()
    {
        return $this->subject('New Job Application - ' . $this->job->title)
                    ->view('emails.new-application')
                    ->with([
                        'job' => $this->job,
                        'applicant' => $this->applicant,
                    ]);
    }
}