<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
           'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            // 'parent_id' => 'required|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.required' => 'Please select your supervisor.',
        ];
    }
}