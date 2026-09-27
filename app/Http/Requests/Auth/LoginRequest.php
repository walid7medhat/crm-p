<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Coordinates are optional: the sign-in page blocks users who deny location,
        // but a device that can't get a GPS fix (timeout / unavailable) sends null
        // and the controller falls back to IP-based location.
        return [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }
}
