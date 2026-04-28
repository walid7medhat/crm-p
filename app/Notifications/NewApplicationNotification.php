<?php

namespace App\Notifications;

use App\Models\Job;
use App\Models\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewApplicationNotification extends Notification
{
    use Queueable;

    protected $job;
    protected $applicant;

    public function __construct(Job $job, Applicant $applicant)
    {
        $this->job = $job;
        $this->applicant = $applicant;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('📝 New Application: ' . $this->job->title)
            ->greeting('Hello ' . $notifiable->name)
            ->line('A new application has been submitted for the position: **' . $this->job->title . '**')
            ->line('**Applicant Details:**')
            ->line('- Name: ' . $this->applicant->full_name)
            ->line('- Email: ' . $this->applicant->email)
            ->line('- Phone: ' . $this->applicant->phone)
            ->line('- Expected Salary: ' . ($this->applicant->expected_salary ?? 'Not specified'))
            ->action('View Application', url('/api/recruitment/admin/applicants/' . $this->applicant->id))
            ->line('Please review the application and take appropriate action.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'new_application',
            'job_id' => $this->job->id,
            'job_title' => $this->job->title,
            'applicant_id' => $this->applicant->id,
            'applicant_name' => $this->applicant->full_name,
            'message' => 'New application for ' . $this->job->title . ' from ' . $this->applicant->full_name,
            'action_url' => '/recruitment/admin/applicants/' . $this->applicant->id,
        ];
    }
}