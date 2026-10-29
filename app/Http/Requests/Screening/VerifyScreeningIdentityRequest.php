<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class VerifyScreeningIdentityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalizedConsent = [];

        foreach (['consent', 'guardian_consent'] as $field) {
            if ($this->exists($field) && blank($this->input($field))) {
                $normalizedConsent[$field] = 'yes';
            }
        }

        if ($normalizedConsent !== []) {
            $this->merge($normalizedConsent);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity_confirmation' => ['required', 'string', 'max:20'],
            'consent' => ['accepted'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_consent' => ['sometimes', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Marque o consentimento para continuar.',
            'guardian_consent.accepted' => 'O responsável legal precisa aceitar o consentimento para continuar.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return url()->previous().'#confirmacao';
    }
}
