<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'sometimes|string|max:255',
            'reminder_date' => 'sometimes|date',
            'is_completed' => 'sometimes|boolean',
            'reminders' => 'required|array|min:1',
           'reminders.*' => 'integer|min:0',
        ];
    }
}