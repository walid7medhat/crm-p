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
        // Coordinates are optional. A denied or failed device fix sends nulls and
        // must not be replaced with an IP, city, or default position.
        return [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:1000000',
            'captured_at' => 'nullable|date',
        ];
    }
}
