<?php

namespace App\Http\Requests\Bioimpedance;

use App\Domain\Bioimpedance\ScaleModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(['clinical_reference', 'manufacturer_manual', 'clinic_protocol'])],
            'manufacturer' => ['nullable', 'required_if:document_type,manufacturer_manual', 'string', 'max:100'],
            'device_model' => ['nullable', 'required_if:document_type,manufacturer_manual', Rule::in([
                ScaleModel::OMRON_NAME,
                ScaleModel::RELAXMEDIC_NAME,
                ScaleModel::BODYSCAN_PRO_NAME,
            ])],
            'version' => ['required', 'string', 'max:64'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'chunks' => ['required', 'array', 'min:1', 'max:50'],
            'chunks.*.section' => ['required', 'string', 'max:255'],
            'chunks.*.page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'chunks.*.language' => ['nullable', 'string', 'max:16'],
            'chunks.*.tags' => ['nullable', 'array', 'max:20'],
            'chunks.*.tags.*' => ['string', 'max:64'],
            'chunks.*.content' => ['required', 'string', 'min:30', 'max:10000'],
        ];
    }
}
