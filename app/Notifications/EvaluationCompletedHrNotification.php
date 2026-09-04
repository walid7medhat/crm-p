<?php

namespace App\Notifications;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EvaluationCompletedHrNotification extends Notification
{
    use Queueable;

    protected Evaluation $evaluation;

    public function __construct(Evaluation $evaluation)
    {
        $this->evaluation = $evaluation;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $employeeName = $this->evaluation->user->displayName() ?? $this->evaluation->user->name;
        $evaluatorName = $this->evaluation->evaluator?->displayName() ?? $this->evaluation->evaluator?->name ?? 'their manager';

        return [
            'type' => 'evaluation_completed_hr',
            'title' => 'Evaluation Completed',
            'message' => "📋 {$employeeName}'s {$this->evaluation->milestone_months}-month evaluation was completed by {$evaluatorName}.",
            'evaluation_id' => $this->evaluation->id,
            'employee_id' => $this->evaluation->user_id,
            'employee_name' => $employeeName,
        ];
    }
}
