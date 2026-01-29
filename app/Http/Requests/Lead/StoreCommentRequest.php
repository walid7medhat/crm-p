<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'lead_id' => 'required|exists:leads,id',
            'comment' => 'required|string|min:1',
            'attachments' => 'sometimes|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt',
            'mentioned_users' => 'sometimes|array',
            'mentioned_users.*' => 'exists:users,id',
        ];
    }

    public function messages()
    {
        return [
            'lead_id.required' => 'Lead ID is required',
            'lead_id.exists' => 'Lead does not exist',
            'comment.required' => 'Comment text is required',
            'comment.min' => 'Comment cannot be empty',
            'attachments.max' => 'Maximum 5 attachments allowed',
            'attachments.*.max' => 'File size must not exceed 10MB',
            'attachments.*.mimes' => 'Allowed file types: images, pdf, documents, text files',
            'mentioned_users.*.exists' => 'One or more mentioned users do not exist',
        ];
    }
}