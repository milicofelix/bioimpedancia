<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use Illuminate\Validation\ValidationException;

class ApprovedObservationContext
{
    public function for(BioimpedanceAssessment $assessment, array $context): array
    {
        $output = BioimpedanceAiAnalysisOutput::query()
            ->with('request')
            ->whereNotNull('approved_at')
            ->where('validation_status', 'passed')
            ->whereHas('request', fn ($query) => $query->where('bioimpedance_assessment_id', $assessment->id)->where('purpose', 'observation'))
            ->orderByDesc('approved_at')->orderByDesc('id')->first();
        $fingerprint = data_get($output?->request?->structured_context, 'measurement_fingerprint');
        if (! $output || blank($assessment->notes) || $output->professional_observation !== $assessment->notes
            || ($fingerprint !== null && $fingerprint !== ($context['measurement_fingerprint'] ?? null))) {
            throw ValidationException::withMessages([
                'care_guidance' => 'Aprove uma observação correspondente aos dados atuais antes de gerar ou aprovar os próximos cuidados.',
            ]);
        }

        $edited = data_get($output->raw_response, 'professional_observation') !== $output->professional_observation;

        return [
            'output_id' => $output->id,
            'approved_at' => $output->approved_at->toIso8601String(),
            'approved_by_user_id' => $output->approved_by_user_id,
            'text' => $output->professional_observation,
            'text_sha256' => hash('sha256', $output->professional_observation),
            'findings' => $edited ? [] : ($output->structured_claims ?? []),
            'findings_scope' => $edited ? 'Texto editado: não presumir aprovação dos achados originais.' : 'Achados vinculados à observação aprovada.',
        ];
    }
}
