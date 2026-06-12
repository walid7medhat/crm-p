<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Location access is required to sign in. Please allow location and try again.',
            'longitude.required' => 'Location access is required to sign in. Please allow location and try again.',
        ];
    }

    public function rules(): array
    {
        // Location is mandatory in production, but optional in local dev where
        // there is usually no real GPS available.
        $location = app()->environment('local') ? 'nullable' : 'required';

        return [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
            'latitude' => $location . '|numeric|between:-90,90',
            'longitude' => $location . '|numeric|between:-180,180',
        ];
    }
}
