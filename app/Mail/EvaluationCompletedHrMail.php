<?php

namespace App\Mail;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EvaluationCompletedHrMail extends Mailable
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
        $evaluatorName = $this->evaluation->evaluator?->displayName() ?? $this->evaluation->evaluator?->name ?? 'their manager';

        $mail = $this->subject("Evaluation completed: {$employeeName}")
            ->view('emails.evaluation-completed-hr')
            ->with([
                'employeeName' => $employeeName,
                'evaluatorName' => $evaluatorName,
                'milestoneMonths' => $this->evaluation->milestone_months,
                'profileUrl' => config('app.frontend_url') . '/hr/employees/' . $this->employee->id,
            ]);

        $mail->attachData($this->pdfContents, "evaluation-{$this->evaluation->id}.pdf", [
            'mime' => 'application/pdf',
        ]);

        return $mail;
    }
}
