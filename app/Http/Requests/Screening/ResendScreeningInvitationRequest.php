<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class ResendScreeningInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClinicalProfessional()
            && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'expires_in_minutes' => ['nullable', 'integer', 'min:15', 'max:10080'],
        ];
    }
}
