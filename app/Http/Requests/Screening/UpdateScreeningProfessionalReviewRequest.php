<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScreeningProfessionalReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClinicalProfessional() && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'professional_notes' => ['nullable', 'string', 'max:5000'],
            'referral_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'professional_notes' => $this->filled('professional_notes') ? trim((string) $this->input('professional_notes')) : null,
            'referral_notes' => $this->filled('referral_notes') ? trim((string) $this->input('referral_notes')) : null,
        ]);
    }
}
