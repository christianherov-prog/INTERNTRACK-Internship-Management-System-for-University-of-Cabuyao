<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPasswordChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'token'    => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&#^()_+\-=\[\]{};\':"\\|,.<>\/?]/',
                'regex:/^\S*$/',
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.regex' => 'The password must contain uppercase, lowercase, a number, and a special character with no spaces.',
        ];
    }
}
