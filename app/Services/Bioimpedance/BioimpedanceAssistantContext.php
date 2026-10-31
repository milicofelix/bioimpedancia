<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanExtendedMetricCatalog;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceImpedanceReading;
use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement;

class BioimpedanceAssistantContext
{
    private const GENERAL_METRICS = [
        'weight_kg' => ['label' => 'Peso', 'unit' => 'kg'],
        'calculated_bmi' => ['label' => 'IMC calculado', 'unit' => 'kg/m²'],
        'body_fat_percentage' => ['label' => 'Gordura corporal', 'unit' => '%'],
        'skeletal_muscle_percentage' => ['label' => 'Músculo esquelético estimado', 'unit' => '%'],
        'lean_body_mass_kg' => ['label' => 'Massa corporal magra estimada', 'unit' => 'kg'],
        'body_water_percentage' => ['label' => 'Água corporal estimada', 'unit' => '%'],
        'visceral_fat_level' => ['label' => 'Gordura visceral', 'unit' => 'índice do dispositivo'],
        'resting_metabolism_kcal' => ['label' => 'Metabolismo basal estimado', 'unit' => 'kcal/dia'],
        'body_age' => ['label' => 'Idade corporal apresentada pelo equipamento', 'unit' => 'anos'],
    ];

    public function __construct(
        private readonly BioimpedanceSegmentalClinicalContext $segmentalContext,
        private readonly BioimpedanceReportComparison $reportComparison,
    ) {}

    public function for(BioimpedanceAssessment $assessment): array
    {
        $assessment->loadMissing(['client', 'segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);
        $comparison = $this->reportComparison->for($assessment);
        $segmental = $this->segmentalContext->for($assessment);
        $generalMetrics = $this->generalMetrics($assessment);
        $indices = $this->extendedIndices($assessment);
        $impedance = $this->impedance($assessment);
        $history = $this->history($comparison);
        $catalog = collect([
            $this->evidence('assessment:current', 'Avaliação atual', 'Perfil e conjunto atual da medição', $assessment->reference_version),
            ...collect($generalMetrics)->map(fn (array $metric) => $this->evidence(
                $metric['evidence_id'],
                'Avaliação atual',
                $metric['label'],
                $assessment->reference_version,
            ))->all(),
            ...collect($indices)->filter(fn (array $metric) => isset($metric['evidence_id']))->map(fn (array $metric) => $this->evidence(
                $metric['evidence_id'],
                'Avaliação atual',
                $metric['label'],
                $metric['reference_version'] ?? $assessment->reference_version,
            ))->all(),
            ...$this->segmentalEvidence($segmental, $assessment->reference_version),
            ...collect($impedance['readings'] ?? [])->map(fn (array $reading) => $this->evidence(
                $reading['evidence_id'],
                'Impedância bioelétrica da avaliação',
                $this->impedanceLabel($reading),
                $reading['device_version'] ?? $assessment->reference_version,
            ))->all(),
            ...($history['evidence_id'] ? [$this->evidence(
                $history['evidence_id'],
                'Comparação do histórico',
                $history['label'],
                $assessment->reference_version,
            )] : []),
            ...(filled($assessment->notes) ? [$this->evidence(
                'observation:approved',
                'Registro profissional da avaliação',
                'Observação profissional aprovada',
                $assessment->reference_version,
            )] : []),
        ])->filter()->keyBy('id')->values()->all();

        return [
            'measurement_profile' => [
                'equipment' => $assessment->device_model,
                'application' => $assessment->measurement_application,
                'layout' => data_get($assessment->source_metadata, 'report_layout')
                    ?? $assessment->extendedMetrics->first()?->source_layout
                    ?? $assessment->impedanceReadings->first()?->source_layout,
                'athlete_mode' => $assessment->athlete_mode_at_assessment,
                'athlete_mode_label' => match ($assessment->athlete_mode_at_assessment) {
                    true => 'atleta',
                    false => 'padrão',
                    default => 'não informado',
                },
            ],
            'general_metrics' => $generalMetrics,
            'extended_indices' => $indices,
            'body_score_policy' => [
                'available' => $assessment->extendedMetrics->contains('metric_code', BodyscanExtendedMetricCatalog::BODY_SCORE),
                'sent_to_clinical_reasoning' => false,
                'reason' => 'Pontuação proprietária do aplicativo; não é medida clínica.',
            ],
            'segmental_analysis' => $segmental,
            'impedance' => $impedance,
            'history_comparison' => $history,
            'safety_limits' => [
                'Não diagnosticar assimetria clínica, sarcopenia ou perda muscular a partir das estimativas.',
                'Idade corporal é uma estimativa proprietária e não representa idade biológica real.',
                'Peso-alvo do aplicativo não é peso ideal prescritivo.',
                'WHR e ASMI são índices do equipamento e não sustentam diagnóstico isolado.',
                'Pontuação corporal não participa da interpretação clínica.',
                'Perfis padrão e atleta usam contextos de cálculo diferentes e não devem ser tratados automaticamente como melhora ou piora.',
            ],
            'evidence_catalog' => $catalog,
            'evidence_ids' => collect($catalog)->pluck('id')->values()->all(),
        ];
    }

    private function generalMetrics(BioimpedanceAssessment $assessment): array
    {
        return collect(self::GENERAL_METRICS)
            ->map(function (array $definition, string $field) use ($assessment): ?array {
                $value = $assessment->{$field};
                if ($value === null) {
                    return null;
                }

                return [
                    'evidence_id' => 'assessment:metric:'.$field,
                    'code' => $field,
                    ...$definition,
                    'value' => is_numeric($value) ? (float) $value : $value,
                    'interpretation_scope' => in_array($field, ['body_age', 'visceral_fat_level'], true)
                        ? 'proprietary_non_diagnostic'
                        : 'measurement_estimate',
                ];
            })->filter()->values()->all();
    }

    private function extendedIndices(BioimpedanceAssessment $assessment): array
    {
        return $assessment->extendedMetrics
            ->whereIn('metric_code', [BodyscanExtendedMetricCatalog::WHR, BodyscanExtendedMetricCatalog::ASMI])
            ->map(fn ($metric) => [
                'evidence_id' => 'assessment:extended:'.$metric->metric_code,
                'code' => $metric->metric_code,
                'label' => $metric->original_label,
                'value' => $metric->numeric_value === null ? $metric->text_value : (float) $metric->numeric_value,
                'unit' => $metric->unit,
                'application_classification' => $metric->application_classification,
                'reference_version' => $metric->reference_version,
                'interpretation_scope' => 'equipment_index_non_diagnostic',
            ])->values()->all();
    }

    private function impedance(BioimpedanceAssessment $assessment): array
    {
        $relevant = $assessment->impedanceReadings
            ->filter(fn (BioimpedanceImpedanceReading $reading) => $reading->impedance_ohms !== null && $reading->reading_state !== 'unreadable')
            ->map(fn (BioimpedanceImpedanceReading $reading) => [
                'evidence_id' => 'assessment:impedance:'.$reading->frequency_khz.':'.$reading->region.':'.$reading->side,
                'frequency_khz' => $reading->frequency_khz,
                'region' => $reading->region,
                'side' => $reading->side,
                'impedance_ohms' => (float) $reading->impedance_ohms,
                'reading_state' => $reading->reading_state,
                'device_version' => $reading->device_version,
            ])->values()->all();

        return [
            'available' => count($relevant) > 0,
            'relevant_to_reasoning' => count($relevant) > 0,
            'interpretation_scope' => 'technical_integrity_and_comparison_only',
            'readings' => $relevant,
        ];
    }

    private function history(array $comparison): array
    {
        $comparability = $comparison['comparability'] ?? [];
        $previous = $comparison['previous'] ?? null;
        $automatic = (bool) ($comparability['automatic_interpretation_allowed'] ?? false);

        return [
            'evidence_id' => $previous ? 'assessment:history-profile' : null,
            'has_previous' => $previous !== null,
            'level' => $comparability['level'] ?? 'baseline',
            'label' => $comparability['label'] ?? 'Linha de base',
            'message' => $comparability['message'] ?? null,
            'warnings' => $comparability['warnings'] ?? [],
            'automatic_interpretation_allowed' => $automatic,
            'current_profile' => $comparability['current_profile'] ?? null,
            'previous_profile' => $comparability['previous_profile'] ?? null,
            'previous_assessment' => $previous,
            'comparable_metrics' => $automatic ? ($comparison['metrics'] ?? []) : [],
            'impedance_comparison' => $automatic ? ($comparison['impedance'] ?? null) : null,
        ];
    }

    private function segmentalEvidence(?array $segmental, ?string $fallbackVersion): array
    {
        if (! $segmental) {
            return [];
        }

        return collect($segmental['current'] ?? [])
            ->concat(data_get($segmental, 'previous_same_model.measurements', []))
            ->map(fn (array $measurement) => $this->evidence(
                $measurement['evidence_id'],
                'Avaliação segmentar',
                $this->segmentalLabel($measurement),
                $measurement['reference_version'] ?? $fallbackVersion,
            ))->all();
    }

    private function evidence(string $id, string $document, string $section, ?string $version): array
    {
        return compact('id', 'document', 'section', 'version') + [
            'publication_status' => 'internal_clinical_record',
            'url' => null,
            'checksum' => null,
        ];
    }

    private function segmentalLabel(array $measurement): string
    {
        $type = ($measurement['type'] ?? null) === BioimpedanceSegmentalMeasurement::TYPE_FAT ? 'Gordura' : 'Músculo';
        $region = match ([$measurement['region'] ?? null, $measurement['side'] ?? null]) {
            ['arm', 'left'] => 'braço esquerdo', ['arm', 'right'] => 'braço direito',
            ['trunk', 'center'] => 'tronco', ['leg', 'left'] => 'perna esquerda',
            ['leg', 'right'] => 'perna direita', default => 'região não identificada',
        };

        return $type.' segmentar - '.$region;
    }

    private function impedanceLabel(array $reading): string
    {
        return 'Impedância '.$reading['frequency_khz'].' kHz - '.$reading['region'].'/'.$reading['side'];
    }
}
