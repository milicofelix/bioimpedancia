<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class StorePaperScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClinicalProfessional() && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'bioimpedance_client_id' => ['required', 'integer', 'exists:bioimpedance_clients,id'],
            'screening_questionnaire_id' => ['required', 'integer', 'exists:screening_questionnaires,id'],
            'paper_completed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'paper_completed_on.required' => 'Informe a data em que o cliente respondeu ao formulário em papel.',
            'paper_completed_on.date_format' => 'Informe uma data válida para o formulário.',
            'paper_completed_on.before_or_equal' => 'A data do formulário não pode estar no futuro.',
        ];
    }
}
