<?php

namespace App\Http\Requests\Student;

use App\Support\OrganizationTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreHteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'student';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'organization_type' => OrganizationTypes::validationRule(false),
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function passedValidation(): void
    {
        $resolved = OrganizationTypes::resolveForStorage($this->input('organization_type'));
        if (! $resolved['ok']) {
            throw ValidationException::withMessages([
                'organization_type' => [$resolved['message']],
            ]);
        }

        $this->merge([
            'organization_type' => $resolved['value'],
        ]);
    }
}
