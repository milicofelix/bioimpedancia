<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use Illuminate\Validation\ValidationException;

class AssistantApprovalGuard
{
    public function __construct(
        private readonly BioimpedanceAssistantContext $assistantContext,
        private readonly ApprovedObservationContext $approvedObservation,
    ) {}

    // The caller must keep the approval and its audit in the same transaction.
    public function observation(BioimpedanceAssessment $assessment, ?int $outputId): BioimpedanceAssessment
    {
        $assessment = $this->lock($assessment);
        if ($outputId === null) {
            return $assessment;
        }
        $output = BioimpedanceAiAnalysisOutput::query()->with('request')
            ->whereKey($outputId)->where('validation_status', 'passed')
            ->whereHas('request', fn ($query) => $query->where('bioimpedance_assessment_id', $assessment->id)->where('purpose', 'observation'))
            ->first();
        $current = $this->assistantContext->for($assessment);
        $fingerprint = data_get($output?->request?->structured_context, 'measurement_fingerprint');
        if (! $output || ($fingerprint !== null && $fingerprint !== $current['measurement_fingerprint'])) {
            throw ValidationException::withMessages(['notes' => 'A sugestão não corresponde à avaliação atual. Gere uma nova observação antes de aprovar.']);
        }

        return $assessment;
    }

    public function careGuidance(BioimpedanceAssessment $assessment, BioimpedanceAiAnalysisOutput $output): BioimpedanceAssessment
    {
        $assessment = $this->lock($assessment);
        $context = $this->assistantContext->for($assessment);
        $approved = $this->approvedObservation->for($assessment, $context);
        if (data_get($output->request->structured_context, 'measurement_fingerprint') !== $context['measurement_fingerprint']
            || ! $this->approvedObservation->matchesSnapshot(data_get($output->request->structured_context, 'approved_observation'), $approved)) {
            throw ValidationException::withMessages(['care_guidance' => 'A avaliação ou a observação aprovada mudou. Gere novamente os próximos cuidados antes de aprovar.']);
        }

        return $assessment;
    }

    private function lock(BioimpedanceAssessment $assessment): BioimpedanceAssessment
    {
        $locked = BioimpedanceAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
        abort_if($locked->canceled_at, 422, 'Avaliação cancelada não pode receber conteúdo aprovado.');

        return $locked;
    }
}
