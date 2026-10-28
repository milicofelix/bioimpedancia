<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\ScaleModel;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement;

class BioimpedanceSegmentalClinicalContext
{
    public function for(BioimpedanceAssessment $assessment): ?array
    {
        if ($assessment->device_model !== ScaleModel::BODYSCAN_PRO_NAME) {
            return null;
        }

        $assessment->loadMissing('segmentalMeasurements');
        if ($assessment->segmentalMeasurements->isEmpty()) {
            return null;
        }

        $previous = BioimpedanceAssessment::query()
            ->where('bioimpedance_client_id', $assessment->bioimpedance_client_id)
            ->where('device_model', $assessment->device_model)
            ->where('id', '!=', $assessment->id)
            ->whereNull('canceled_at')
            ->where('evaluated_at', '<', $assessment->evaluated_at)
            ->with('segmentalMeasurements')
            ->latest('evaluated_at')
            ->first();

        $measurements = $assessment->segmentalMeasurements
            ->map(fn (BioimpedanceSegmentalMeasurement $measurement) => $this->measurement($measurement))
            ->values();
        $previousMeasurements = $previous?->segmentalMeasurements?->isNotEmpty()
            ? $previous->segmentalMeasurements
                ->map(fn (BioimpedanceSegmentalMeasurement $measurement) => $this->measurement($measurement, 'history'))
                ->values()
            : collect();

        return [
            'measurement_context' => [
                'evaluated_at' => $assessment->evaluated_at?->toIso8601String(),
                'review_confirmed_at' => data_get($assessment->source_metadata, 'review_confirmed_at'),
                'quality_issues_reviewed' => data_get($assessment->source_metadata, 'ocr_quality_issues', []),
                'side_consistency_reviewed' => data_get($assessment->source_metadata, 'side_consistency'),
                'unrecorded_conditions' => ['hidratação', 'alimentação', 'horário habitual', 'atividade física recente'],
            ],
            'interpretation_limits' => [
                'Os valores segmentares são estimativas do equipamento e não constituem diagnóstico.',
                'Diferenças entre lados não comprovam assimetria clínica.',
                'Variação muscular não comprova perda muscular nem preservação de força ou função.',
                'A comparação deve considerar hidratação, alimentação, horário, atividade recente e demais condições da medição, que não são registradas pela balança.',
            ],
            'current' => $measurements->all(),
            'previous_same_model' => $previous?->segmentalMeasurements?->isNotEmpty() ? [
                'assessment_id' => $previous->id,
                'evaluated_at' => $previous->evaluated_at?->toIso8601String(),
                'measurements' => $previousMeasurements->all(),
            ] : null,
            'current_evidence_ids' => $measurements->pluck('evidence_id')->all(),
            'evidence_ids' => $measurements->concat($previousMeasurements)->pluck('evidence_id')->all(),
        ];
    }

    private function measurement(BioimpedanceSegmentalMeasurement $measurement, string $scope = 'current'): array
    {
        return [
            'evidence_id' => 'assessment:segmental:'.$scope.':'.$measurement->type.':'.$measurement->region.':'.$measurement->side,
            'type' => $measurement->type,
            'region' => $measurement->region,
            'side' => $measurement->side,
            'mass_kg' => (float) $measurement->mass_kg,
            'reference_percentage' => (float) $measurement->reference_percentage,
            'device_classification' => $measurement->classification,
            'reference_version' => $measurement->reference_version,
        ];
    }
}
