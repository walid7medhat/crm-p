<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'lead_id' => 'required|exists:leads,id',
            'title' => 'required|string|max:255',
            'reminder_date' => 'required|date|after_or_equal:now',
            'is_completed' => 'boolean',
              'reminders' => 'required|array|min:1',
           'reminders.*' => 'integer|min:0',

        ];
    }

    public function messages()
    {
        return [
            'lead_id.required' => 'Lead ID is required',
            'lead_id.exists' => 'Lead does not exist',
            'title.required' => 'Activity title is required',
            'reminder_date.required' => 'Reminder date is required',
            'reminder_date.date' => 'Reminder date must be a valid date',
            'reminder_date.after_or_equal' => 'Reminder date must be today or in the future',
            'is_completed.boolean' => 'Completed status must be true or false',
        ];
    }
}