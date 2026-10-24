<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use Illuminate\Support\Collection;

class BioimpedanceReportComparison
{
    private const TREND_METRICS = [
        'weight_kg' => ['label' => 'Peso', 'unit' => 'kg', 'decimals' => 1],
        'body_fat_percentage' => ['label' => 'Gordura corporal', 'unit' => '%', 'decimals' => 1],
    ];

    private const METRICS = [
        'weight_kg' => ['label' => 'Peso', 'unit' => 'kg', 'decimals' => 1],
        'body_fat_percentage' => ['label' => 'Gordura corporal', 'unit' => '%', 'decimals' => 1],
        'skeletal_muscle_percentage' => ['label' => 'Músculo esquelético', 'unit' => '%', 'decimals' => 1],
        'visceral_fat_level' => ['label' => 'Gordura visceral', 'unit' => 'nível', 'decimals' => 1],
        'lean_body_mass_kg' => ['label' => 'Massa corporal magra', 'unit' => 'kg', 'decimals' => 1],
        'body_water_percentage' => ['label' => 'Água corporal', 'unit' => '%', 'decimals' => 1],
    ];

    public function for(BioimpedanceAssessment $assessment): array
    {
        if ($assessment->canceled_at) {
            return ['previous' => null, 'metrics' => [], 'trends' => [], 'history_count' => 0];
        }

        $history = $assessment->client->assessments()
            ->where('id', '!=', $assessment->id)
            ->where('device_model', $assessment->device_model)
            ->whereNull('canceled_at')
            ->where('evaluated_at', '<', $assessment->evaluated_at)
            ->latest('evaluated_at')
            ->limit(5)
            ->get()
            ->sortBy('evaluated_at')
            ->values()
            ->push($assessment);

        $previous = $history->slice(0, -1)->last();

        if (! $previous) {
            return [
                'previous' => null,
                'metrics' => [],
                'trends' => $this->trends($assessment, $history),
                'history_count' => $history->count(),
            ];
        }

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
            ->all();

        return [
            'previous' => [
                'id' => $previous->id,
                'evaluated_at' => $previous->evaluated_at?->toIso8601String(),
                'device_model' => $previous->device_model,
            ],
            'metrics' => $metrics,
            'trends' => $this->trends($assessment, $history),
            'history_count' => $history->count(),
        ];
    }

    private function trends(BioimpedanceAssessment $assessment, Collection $history): array
    {
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
}
