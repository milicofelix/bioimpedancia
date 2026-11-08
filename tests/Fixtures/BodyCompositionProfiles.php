<?php

namespace Tests\Fixtures;

use App\Services\Bioimpedance\AssessmentFindings;

/** Anonymous profiles reproducing the relevant numerical scenarios in the audit. */
class BodyCompositionProfiles
{
    public static function context(bool $maleAthlete): array
    {
        $metrics = [
            ['weight_kg', 'Peso', $maleAthlete ? 92.1 : 75.1, 'kg', null],
            ['calculated_bmi', 'IMC calculado', $maleAthlete ? 30.4 : 27.6, 'kg/m²', $maleAthlete ? 'Obesidade grau I' : 'Sobrepeso'],
            ['body_fat_percentage', 'Gordura corporal', $maleAthlete ? 27.1 : 41.4, '%', null],
            ['skeletal_muscle_percentage', 'Músculo esquelético', $maleAthlete ? 42.1 : 32.3, '%', null],
        ];
        $regions = [['arm', 'left'], ['arm', 'right'], ['leg', 'left'], ['leg', 'right'], ['trunk', 'center']];
        $segments = [];
        foreach (['fat', 'muscle'] as $type) {
            $values = $type === 'fat'
                ? ($maleAthlete ? [267, 266.5, 238, 238.2, 335.7] : [225.8, 225.2, 194.8, 194.4, 316.5])
                : ($maleAthlete ? [116.4, 116.4, 114.5, 113.4, 107.5] : [93.7, 94.6, 96.2, 96.1, 90.9]);
            foreach ($regions as $index => [$region, $side]) {
                $segments[] = [
                    'evidence_id' => "assessment:segmental:current:{$type}:{$region}:{$side}",
                    'type' => $type, 'region' => $region, 'side' => $side,
                    'mass_kg' => null, 'reference_percentage' => $values[$index],
                    'reference_min_percentage' => $type === 'fat' || $region === 'arm' ? 80 : 90,
                    'reference_max_percentage' => $type === 'fat' ? 160 : ($region === 'arm' ? 115 : 110),
                    'device_classification' => $type === 'fat' || ($maleAthlete && $region === 'leg') ? 'high' : 'standard',
                    'classification_origin' => 'equipment_report',
                ];
            }
        }
        $context = [
            'age_at_assessment' => $maleAthlete ? 45 : 54,
            'measurement_profile' => ['sex' => $maleAthlete ? 'male' : 'female', 'athlete_mode' => $maleAthlete],
            'general_metrics' => array_map(fn ($metric) => [
                'code' => $metric[0], 'evidence_id' => 'assessment:metric:'.$metric[0],
                'label' => $metric[1], 'value' => $metric[2], 'unit' => $metric[3],
                'classification' => $metric[4], 'classification_origin' => $metric[4] ? 'system_reference' : null,
                'tone' => $metric[4] ? 'warning' : 'neutral',
            ], $metrics),
            'segmental_analysis' => ['current' => $segments],
            'history_comparison' => ['has_previous' => false, 'automatic_interpretation_allowed' => false],
            'approved_observation' => ['text' => 'Síntese revisada pelo profissional.'],
            'professional_observation' => 'Síntese revisada pelo profissional.',
            'extended_indices' => [], 'warnings' => [],
        ];
        $context['findings'] = (new AssessmentFindings)->from($context);
        $context['evidence_ids'] = ['assessment:current', 'observation:approved', ...array_column($context['findings'], 'id')];

        return $context;
    }
}
