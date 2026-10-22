<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AbsenceNoticeMail extends Mailable
{
    public function __construct(public string $userName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Attendance Notice – No Attendance Record');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.absence-notice');
    }
}