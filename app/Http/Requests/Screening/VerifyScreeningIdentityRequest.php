<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class VerifyScreeningIdentityRequest extends FormRequest
{
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
            'guardian_consent' => ['nullable', 'accepted'],
        ];
    }
}
