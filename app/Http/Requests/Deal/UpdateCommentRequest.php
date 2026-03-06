<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'comment' => 'required|string|min:1',
            'mentioned_users' => 'sometimes|array',
            'mentioned_users.*' => 'exists:users,id',
        ];
    }
}