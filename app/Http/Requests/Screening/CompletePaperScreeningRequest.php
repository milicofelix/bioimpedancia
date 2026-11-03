<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class CompletePaperScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClinicalProfessional() && $this->user()->isActive()
            && ($this->user()->isAdmin() || $this->route('session')?->requested_by_user_id === $this->user()->id);
    }

    public function rules(): array
    {
        $rules = [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'integer', 'min:1'],
            'completion_confirmed' => ['required', 'accepted'],
            'paper_identity_confirmed' => ['required', 'accepted'],
            'paper_consent_confirmed' => ['required', 'accepted'],
        ];
        if ($this->route('session')?->is_minor_at_application) {
            $rules['paper_guardian_consent_confirmed'] = ['required', 'accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'answers.required' => 'Transcreva todas as respostas antes de finalizar.',
            'completion_confirmed.required' => 'Confira a transcrição com o documento original antes de finalizar.',
            'completion_confirmed.accepted' => 'Confira a transcrição com o documento original antes de finalizar.',
            'paper_identity_confirmed.required' => 'Confirme que o formulário pertence ao cliente selecionado.',
            'paper_identity_confirmed.accepted' => 'Confirme que o formulário pertence ao cliente selecionado.',
            'paper_consent_confirmed.required' => 'Confirme que o consentimento foi obtido e consta no documento em papel.',
            'paper_consent_confirmed.accepted' => 'Confirme que o consentimento foi obtido e consta no documento em papel.',
            'paper_guardian_consent_confirmed.required' => 'Confira o consentimento do responsável legal no documento em papel.',
            'paper_guardian_consent_confirmed.accepted' => 'Confira o consentimento do responsável legal no documento em papel.',
        ];
    }
}
