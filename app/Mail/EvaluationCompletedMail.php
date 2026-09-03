<?php

namespace App\Mail;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EvaluationCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Evaluation $evaluation,
        public User $employee,
        public string $pdfContents,
    ) {
    }

    public function build()
    {
        $employeeName = $this->employee->displayName() ?? $this->employee->name;

        $mail = $this->subject("Your {$this->evaluation->milestone_months}-month evaluation is ready")
            ->view('emails.evaluation-completed')
            ->with([
                'employeeName' => $employeeName,
                'milestoneMonths' => $this->evaluation->milestone_months,
            ]);

        $mail->attachData($this->pdfContents, "evaluation-{$this->evaluation->id}.pdf", [
            'mime' => 'application/pdf',
        ]);

        return $mail;
    }
}
