<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class ApproveScreeningQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClinicalProfessional()
            && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'profession' => ['required', 'string', 'max:120'],
            'professional_registration' => ['required', 'string', 'max:80'],
            'recall_period_confirmed' => ['accepted'],
            'questions_confirmed' => ['accepted'],
            'ranges_confirmed' => ['accepted'],
            'disclaimers_confirmed' => ['accepted'],
            'electronic_signature_confirmed' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.accepted' => 'Confirme este item para registrar a aprovação editorial.',
            'profession.required' => 'Informe a profissão do responsável pela aprovação.',
            'professional_registration.required' => 'Informe o conselho e o número de registro profissional.',
        ];
    }
}
