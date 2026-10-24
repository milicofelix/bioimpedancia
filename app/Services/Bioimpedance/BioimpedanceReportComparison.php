<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAssessment;

class BioimpedanceReportComparison
{
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
        $previous = $assessment->client->assessments()
            ->where('id', '!=', $assessment->id)
            ->where('device_model', $assessment->device_model)
            ->whereNull('canceled_at')
            ->where('evaluated_at', '<', $assessment->evaluated_at)
            ->latest('evaluated_at')
            ->first();

        if (! $previous) {
            return ['previous' => null, 'metrics' => []];
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
        ];
    }
}
