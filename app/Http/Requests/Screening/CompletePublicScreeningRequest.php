<?php

namespace App\Http\Requests\Screening;

use Illuminate\Foundation\Http\FormRequest;

class CompletePublicScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'integer', 'min:1'],
            'completion_confirmed' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required' => 'Responda todas as perguntas antes de finalizar.',
            'answers.*.required' => 'Selecione uma alternativa para cada pergunta.',
            'completion_confirmed.accepted' => 'Confirme que deseja finalizar e enviar suas respostas.',
        ];
    }
}
