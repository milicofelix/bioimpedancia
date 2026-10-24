<?php

namespace App\Http\Requests\Bioimpedance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['review', 'approve', 'publish', 'supersede'])],
            'replacement_document_id' => ['required_if:action,supersede', 'nullable', 'integer', 'exists:bioimpedance_knowledge_documents,id'],
        ];
    }
}
