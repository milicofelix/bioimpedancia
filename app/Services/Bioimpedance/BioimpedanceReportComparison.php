<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\ScaleModel;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceImpedanceReading;
use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement;
use Illuminate\Support\Collection;

class BioimpedanceReportComparison
{
    private const TREND_METRICS = [
        'weight_kg' => ['label' => 'Peso', 'unit' => 'kg', 'decimals' => 1],
        'body_fat_percentage' => ['label' => 'Gordura corporal', 'unit' => '%', 'decimals' => 1],
    ];

    private const METRICS = [
        'weight_kg' => ['label' => 'Peso', 'unit' => 'kg', 'decimals' => 1, 'metric_type' => 'mass'],
        'body_fat_percentage' => ['label' => 'Gordura corporal', 'unit' => '%', 'decimals' => 1, 'metric_type' => 'percentage'],
        'skeletal_muscle_percentage' => ['label' => 'Músculo esquelético', 'unit' => '%', 'decimals' => 1, 'metric_type' => 'percentage'],
        'visceral_fat_level' => ['label' => 'Gordura visceral', 'unit' => 'nível', 'decimals' => 1, 'metric_type' => 'index'],
        'lean_body_mass_kg' => ['label' => 'Massa corporal magra', 'unit' => 'kg', 'decimals' => 1, 'metric_type' => 'mass'],
        'body_water_percentage' => ['label' => 'Água corporal', 'unit' => '%', 'decimals' => 1, 'metric_type' => 'percentage'],
    ];

    public function for(BioimpedanceAssessment $assessment): array
    {
        if ($assessment->canceled_at) {
            return ['previous' => null, 'comparability' => null, 'metrics' => [], 'trends' => [], 'segmental' => null, 'impedance' => null, 'history_count' => 0];
        }

        $assessment->loadMissing(['segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);

        $history = $assessment->client->assessments()
            ->where('id', '!=', $assessment->id)
            ->where('device_model', $assessment->device_model)
            ->whereNull('canceled_at')
            ->where('evaluated_at', '<', $assessment->evaluated_at)
            ->with(['segmentalMeasurements', 'extendedMetrics', 'impedanceReadings'])
            ->latest('evaluated_at')
            ->limit(20)
            ->get()
            ->sortBy('evaluated_at')
            ->values()
            ->push($assessment);

        $previous = $history->slice(0, -1)->last();

        if (! $previous) {
            return [
                'previous' => null,
                'metrics' => [],
                'comparability' => $this->comparability($assessment, null),
                'trends' => $this->trends($assessment, $history),
                'segmental' => $this->segmental($assessment, null),
                'impedance' => $this->impedance($assessment, null),
                'history_count' => $history->count(),
            ];
        }

        $comparability = $this->comparability($assessment, $previous);
        $metrics = collect(self::METRICS)
            ->map(function (array $definition, string $field) use ($assessment, $previous) {
                $current = $assessment->{$field};
                $prior = $previous->{$field};
                if ($current === null || $prior === null) {
                    return null;
                }
                $decimals = $field === 'visceral_fat_level' && $assessment->device_model !== 'Relaxmedic'
                    ? 0
                    : $definition['decimals'];

                return [
                    ...$definition,
                    'decimals' => $decimals,
                    'field' => $field,
                    'current' => (float) $current,
                    'previous' => (float) $prior,
                    'delta' => round((float) $current - (float) $prior, $decimals),
                ];
            })
            ->filter()
            ->values()
            ->concat($this->extendedMetrics($assessment, $previous))
            ->values()
            ->all();

        return [
            'previous' => [
                'id' => $previous->id,
                'evaluated_at' => $previous->evaluated_at?->toIso8601String(),
                'device_model' => $previous->device_model,
                'measurement_application' => $previous->measurement_application,
                'report_layout' => $this->layout($previous),
                'athlete_mode_at_assessment' => $previous->athlete_mode_at_assessment,
            ],
            'comparability' => $comparability,
            'metrics' => $metrics,
            'trends' => $this->trends($assessment, $history),
            'segmental' => $this->segmental($assessment, $previous),
            'impedance' => $this->impedance($assessment, $previous),
            'history_count' => $history->count(),
        ];
    }

    private function impedance(BioimpedanceAssessment $current, ?BioimpedanceAssessment $previous): ?array
    {
        if ($current->impedanceReadings->isEmpty()) {
            return null;
        }

        $currentLayout = $current->impedanceReadings->first()?->source_layout;
        $previousReadings = $previous?->impedanceReadings?->filter(
            fn (BioimpedanceImpedanceReading $reading) => $reading->source_layout === $currentLayout,
        )->keyBy(fn (BioimpedanceImpedanceReading $reading) => $reading->frequency_khz.'|'.$reading->region.'|'.$reading->side) ?? collect();

        return [
            'is_baseline' => $previousReadings->isEmpty(),
            'previous_assessment_id' => $previousReadings->isEmpty() ? null : $previous?->id,
            'source_layout' => $currentLayout,
            'interpretation_scope' => 'technical_non_diagnostic',
            'readings' => $current->impedanceReadings
                ->sortBy(fn (BioimpedanceImpedanceReading $reading) => $reading->frequency_khz.'|'.$reading->region.'|'.$reading->side)
                ->values()
                ->map(function (BioimpedanceImpedanceReading $reading) use ($previousReadings): array {
                    $key = $reading->frequency_khz.'|'.$reading->region.'|'.$reading->side;
                    $prior = $previousReadings->get($key);
                    $currentValue = $reading->impedance_ohms === null ? null : (float) $reading->impedance_ohms;
                    $previousValue = $prior?->impedance_ohms === null ? null : (float) $prior->impedance_ohms;

                    return [
                        'frequency_khz' => $reading->frequency_khz,
                        'region' => $reading->region,
                        'side' => $reading->side,
                        'reading_state' => $reading->reading_state,
                        'current_ohms' => $currentValue,
                        'previous_ohms' => $previousValue,
                        'delta_ohms' => $currentValue !== null && $previousValue !== null
                            ? round($currentValue - $previousValue, 2)
                            : null,
                    ];
                })->all(),
        ];
    }

    private function segmental(BioimpedanceAssessment $current, ?BioimpedanceAssessment $previous): ?array
    {
        if ($current->device_model !== ScaleModel::BODYSCAN_PRO_NAME || $current->segmentalMeasurements->isEmpty()) {
            return null;
        }

        $previousSegments = $previous?->segmentalMeasurements?->keyBy(
            fn (BioimpedanceSegmentalMeasurement $item) => $item->type.':'.$item->region.':'.$item->side
        ) ?? collect();
        $sameReference = $this->sameSegmentalReference($current->segmentalMeasurements, $previousSegments->values());

        $regions = $current->segmentalMeasurements
            ->sortBy(fn (BioimpedanceSegmentalMeasurement $item) => array_search(
                ['region' => $item->region, 'side' => $item->side],
                BioimpedanceSegmentalMeasurement::EXPECTED_SEGMENTS,
                true,
            ))
            ->map(function (BioimpedanceSegmentalMeasurement $item) use ($previousSegments, $sameReference): array {
                $prior = $previousSegments->get($item->type.':'.$item->region.':'.$item->side);

                return [
                    'type' => $item->type,
                    'region' => $item->region,
                    'side' => $item->side,
                    'label' => $this->segmentLabel($item->region, $item->side),
                    'mass_kg' => $this->change($prior?->mass_kg, $item->mass_kg),
                    'reference_percentage' => $this->change(
                        $sameReference ? $prior?->reference_percentage : null,
                        $item->reference_percentage,
                    ),
                    'previous_reference_percentage' => $prior?->reference_percentage === null ? null : (float) $prior->reference_percentage,
                ];
            })
            ->values();

        return [
            'is_baseline' => $previous === null || $previousSegments->isEmpty(),
            'has_previous' => $previous !== null && $previousSegments->isNotEmpty(),
            'same_reference' => $sameReference,
            'reference_warning' => $previousSegments->isNotEmpty() && ! $sameReference
                ? 'As referências segmentares são diferentes. Os percentuais são exibidos sem cálculo de variação.'
                : null,
            'regions' => $regions->all(),
            'laterality' => collect(BioimpedanceSegmentalMeasurement::TYPES)
                ->flatMap(fn (string $type) => collect([
                    BioimpedanceSegmentalMeasurement::REGION_ARM,
                    BioimpedanceSegmentalMeasurement::REGION_LEG,
                ])->map(fn (string $region) => $this->laterality($regions, $type, $region)))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function laterality(Collection $regions, string $type, string $region): ?array
    {
        $left = $regions->first(fn (array $item) => $item['type'] === $type && $item['region'] === $region && $item['side'] === 'left');
        $right = $regions->first(fn (array $item) => $item['type'] === $type && $item['region'] === $region && $item['side'] === 'right');
        if (! $left || ! $right) {
            return null;
        }

        return [
            'type' => $type,
            'region' => $region,
            'label' => $region === 'arm' ? 'Braços' : 'Pernas',
            'current_mass_difference_kg' => round(abs($left['mass_kg']['current'] - $right['mass_kg']['current']), 2),
            'previous_mass_difference_kg' => $left['mass_kg']['previous'] !== null && $right['mass_kg']['previous'] !== null
                ? round(abs($left['mass_kg']['previous'] - $right['mass_kg']['previous']), 2)
                : null,
            'current_reference_difference' => round(abs($left['reference_percentage']['current'] - $right['reference_percentage']['current']), 1),
            'previous_reference_difference' => $left['reference_percentage']['previous'] !== null && $right['reference_percentage']['previous'] !== null
                ? round(abs($left['reference_percentage']['previous'] - $right['reference_percentage']['previous']), 1)
                : null,
        ];
    }

    private function change(mixed $previous, mixed $current): array
    {
        return [
            'previous' => $previous === null ? null : (float) $previous,
            'current' => (float) $current,
            'delta' => $previous === null ? null : round((float) $current - (float) $previous, 2),
        ];
    }

    private function segmentLabel(string $region, string $side): string
    {
        return match ([$region, $side]) {
            ['arm', 'left'] => 'Braço esquerdo',
            ['arm', 'right'] => 'Braço direito',
            ['trunk', 'center'] => 'Tronco',
            ['leg', 'left'] => 'Perna esquerda',
            ['leg', 'right'] => 'Perna direita',
            default => ucfirst($region),
        };
    }

    private function trends(BioimpedanceAssessment $assessment, Collection $history): array
    {
        $history = $history
            ->filter(fn (BioimpedanceAssessment $item) => $this->sameCalculationProfile($assessment, $item))
            ->take(-6)
            ->values();
        $definitions = self::TREND_METRICS;
        $muscleField = $assessment->device_model === 'Relaxmedic' ? 'muscle_mass_kg' : 'skeletal_muscle_percentage';
        $definitions[$muscleField] = [
            'label' => $assessment->device_model === 'Relaxmedic' ? 'Massa muscular' : 'Músculo esquelético',
            'unit' => $assessment->device_model === 'Relaxmedic' ? 'kg' : '%',
            'decimals' => 1,
        ];

        return collect($definitions)
            ->map(function (array $definition, string $field) use ($history): ?array {
                $points = $history
                    ->filter(fn (BioimpedanceAssessment $item) => $item->{$field} !== null)
                    ->values()
                    ->map(fn (BioimpedanceAssessment $item) => [
                        'assessment_id' => $item->id,
                        'date' => $item->evaluated_at?->format('d/m/y'),
                        'value' => (float) $item->{$field},
                    ]);

                if ($points->isEmpty()) {
                    return null;
                }

                $minimum = (float) $points->min('value');
                $maximum = (float) $points->max('value');
                $isFlat = abs($maximum - $minimum) < 0.0001;
                $range = max($maximum - $minimum, 0.1);
                $lastIndex = max($points->count() - 1, 1);
                $chartPoints = $points->map(fn (array $point, int $index) => [
                    ...$point,
                    'x' => round(12 + (($index / $lastIndex) * 276), 1),
                    'y' => $isFlat ? 45 : round(72 - ((($point['value'] - $minimum) / $range) * 54), 1),
                ])->all();

                return [
                    ...$definition,
                    'field' => $field,
                    'points' => $chartPoints,
                    'polyline' => collect($chartPoints)->map(fn (array $point) => $point['x'].','.$point['y'])->implode(' '),
                    'minimum' => $minimum,
                    'maximum' => $maximum,
                    'has_evolution' => count($chartPoints) >= 2,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function comparability(BioimpedanceAssessment $current, ?BioimpedanceAssessment $previous): array
    {
        $currentProfile = $this->profile($current);
        if (! $previous) {
            return [
                'level' => 'baseline',
                'label' => 'Linha de base',
                'message' => 'Não há avaliação anterior do mesmo modelo; esta medição será a linha de base.',
                'automatic_interpretation_allowed' => false,
                'checks' => [],
                'warnings' => [],
                'current_profile' => $currentProfile,
                'previous_profile' => null,
            ];
        }

        $previousProfile = $this->profile($previous);
        $sameEquipment = $current->device_model === $previous->device_model;
        $applicationApplicable = $current->device_model === ScaleModel::BODYSCAN_PRO_NAME;
        $sameApplication = ! $applicationApplicable || (filled($current->measurement_application)
            && $current->measurement_application === $previous->measurement_application);
        $athleteModeApplicable = $current->device_model === ScaleModel::BODYSCAN_PRO_NAME;
        $sameAthleteMode = ! $athleteModeApplicable
            || ($current->athlete_mode_at_assessment !== null
                && $previous->athlete_mode_at_assessment !== null
                && $current->athlete_mode_at_assessment === $previous->athlete_mode_at_assessment);
        $sameLayout = filled($currentProfile['layout']) && $currentProfile['layout'] === $previousProfile['layout'];
        $sameSegmentalReference = $this->sameSegmentalReference(
            $current->segmentalMeasurements,
            $previous->segmentalMeasurements,
        );
        $isFitdaysPair = $sameApplication && $current->measurement_application === 'fitdays';
        $isTechnicalPair = $isFitdaysPair
            && $currentProfile['layout'] === 'fitdays_technical_report'
            && $previousProfile['layout'] === 'fitdays_technical_report';
        $applicationChanged = $applicationApplicable
            && filled($current->measurement_application)
            && filled($previous->measurement_application)
            && ! $sameApplication;

        $warnings = collect([
            $applicationApplicable && ! $sameApplication ? 'Os aplicativos de origem são diferentes ou não puderam ser confirmados; somente campos equivalentes são comparados.' : null,
            ! $sameAthleteMode ? 'O perfil de cálculo mudou entre modo atleta e modo padrão, ou não foi informado. Os valores não representam automaticamente melhora ou piora.' : null,
            ! $sameSegmentalReference ? 'A referência segmentar mudou; percentuais de referência não recebem cálculo de variação.' : null,
            $sameApplication && ! $sameLayout ? 'Os layouts são diferentes; somente métricas presentes nos dois relatórios são comparadas.' : null,
        ])->filter()->values()->all();

        $level = match (true) {
            ! $sameEquipment => 'incompatible',
            $applicationChanged => 'limited',
            $isTechnicalPair && $sameAthleteMode && $sameSegmentalReference => 'complete',
            default => 'common_fields',
        };
        $label = match ($level) {
            'complete' => 'Comparação completa',
            'limited' => 'Comparação limitada',
            'incompatible' => 'Comparação indisponível',
            default => 'Comparação das métricas comuns',
        };

        return [
            'level' => $level,
            'label' => $label,
            'message' => $level === 'limited'
                ? 'Fitdays e RelaxFit podem usar algoritmos e referências diferentes. A comparação mostra apenas valores equivalentes, com identificação da origem.'
                : ($level === 'complete'
                    ? 'Mesmo equipamento, aplicativo Fitdays, layout técnico, modo de cálculo e referências segmentares.'
                    : 'Somente campos presentes nos dois relatórios, com o mesmo tipo e unidade, são exibidos.'),
            'automatic_interpretation_allowed' => $sameEquipment && $sameApplication && $sameAthleteMode,
            'checks' => [
                'same_equipment' => $sameEquipment,
                'same_application' => $sameApplication,
                'same_athlete_mode' => $sameAthleteMode,
                'same_layout' => $sameLayout,
                'same_metric_type' => true,
                'same_unit' => true,
                'same_segmental_reference' => $sameSegmentalReference,
            ],
            'warnings' => $warnings,
            'current_profile' => $currentProfile,
            'previous_profile' => $previousProfile,
        ];
    }

    private function profile(BioimpedanceAssessment $assessment): array
    {
        return [
            'equipment' => $assessment->device_model,
            'application' => $assessment->measurement_application,
            'layout' => $this->layout($assessment),
            'athlete_mode' => $assessment->athlete_mode_at_assessment,
        ];
    }

    private function layout(BioimpedanceAssessment $assessment): ?string
    {
        return data_get($assessment->source_metadata, 'report_layout')
            ?? $assessment->extendedMetrics->first()?->source_layout
            ?? $assessment->impedanceReadings->first()?->source_layout;
    }

    private function sameCalculationProfile(BioimpedanceAssessment $current, BioimpedanceAssessment $candidate): bool
    {
        if ($current->id === $candidate->id) {
            return true;
        }

        return $current->device_model === $candidate->device_model
            && $current->measurement_application === $candidate->measurement_application
            && ($current->device_model !== ScaleModel::BODYSCAN_PRO_NAME
                || ($current->athlete_mode_at_assessment !== null
                    && $candidate->athlete_mode_at_assessment !== null
                    && $current->athlete_mode_at_assessment === $candidate->athlete_mode_at_assessment));
    }

    private function sameSegmentalReference(Collection $current, Collection $previous): bool
    {
        if ($current->isEmpty() || $previous->isEmpty()) {
            return true;
        }

        $previousByKey = $previous->keyBy(fn (BioimpedanceSegmentalMeasurement $item) => $item->type.':'.$item->region.':'.$item->side);

        return $current->every(function (BioimpedanceSegmentalMeasurement $item) use ($previousByKey): bool {
            $prior = $previousByKey->get($item->type.':'.$item->region.':'.$item->side);

            return $prior
                && $prior->reference_version === $item->reference_version
                && (float) $prior->reference_min_percentage === (float) $item->reference_min_percentage
                && (float) $prior->reference_max_percentage === (float) $item->reference_max_percentage;
        });
    }

    private function extendedMetrics(BioimpedanceAssessment $current, BioimpedanceAssessment $previous): Collection
    {
        $previousMetrics = $previous->extendedMetrics
            ->filter(fn ($metric) => $metric->numeric_value !== null)
            ->keyBy(fn ($metric) => $metric->metric_code.'|'.mb_strtolower((string) $metric->unit));

        return $current->extendedMetrics
            ->filter(fn ($metric) => $metric->numeric_value !== null)
            ->map(function ($metric) use ($previousMetrics): ?array {
                $prior = $previousMetrics->get($metric->metric_code.'|'.mb_strtolower((string) $metric->unit));
                if (! $prior) {
                    return null;
                }

                return [
                    'field' => 'extended:'.$metric->metric_code,
                    'label' => $metric->original_label,
                    'unit' => $metric->unit ?? '',
                    'decimals' => 2,
                    'metric_type' => 'extended_numeric',
                    'current' => (float) $metric->numeric_value,
                    'previous' => (float) $prior->numeric_value,
                    'delta' => round((float) $metric->numeric_value - (float) $prior->numeric_value, 2),
                ];
            })
            ->filter();
    }
}
