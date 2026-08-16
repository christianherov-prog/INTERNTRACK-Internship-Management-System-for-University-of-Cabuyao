<?php

namespace App\Http\Requests\Auth;

use App\Support\SexOptions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'middle_name'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'suffix'         => ['sometimes', 'nullable', 'string', 'max:30'],
            'name'           => ['sometimes', 'nullable', 'string', 'max:200'],
            'email'          => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact'        => ['sometimes', 'nullable', 'string', 'max:40'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:40'],
            'program'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'course_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'company'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'sex'            => SexOptions::validationRule(false),
        ];
    }
}
