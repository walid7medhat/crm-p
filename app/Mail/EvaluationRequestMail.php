<?php

namespace App\Mail;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EvaluationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Evaluation $evaluation,
        public User $employee,
        public User $manager,
    ) {
    }

    public function build()
    {
        $employeeName = $this->employee->displayName() ?? $this->employee->name;
        $managerName = $this->manager->displayName() ?? $this->manager->name;

        return $this->subject("Evaluation due: {$employeeName} ({$this->evaluation->milestone_months}-month review)")
            ->view('emails.evaluation-request')
            ->with([
                'managerName' => $managerName,
                'employeeName' => $employeeName,
                'milestoneMonths' => $this->evaluation->milestone_months,
                'formUrl' => config('app.frontend_url') . '/evaluations/' . $this->evaluation->id,
            ]);
    }
}
