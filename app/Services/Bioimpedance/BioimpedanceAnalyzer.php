<?php

namespace App\Services\Bioimpedance;

use Carbon\CarbonImmutable;

class BioimpedanceAnalyzer
{
    public const DEVICE_MODEL = 'HBF-514C';

    public const REFERENCE_VERSION = '1.0.0';

    private const BODY_FAT_RANGES = [
        'female' => [
            ['min_age' => 20, 'max_age' => 39, 'normal_min' => 21.0, 'normal_max' => 32.9, 'high_max' => 38.9],
            ['min_age' => 40, 'max_age' => 59, 'normal_min' => 23.0, 'normal_max' => 33.9, 'high_max' => 39.9],
            ['min_age' => 60, 'max_age' => 79, 'normal_min' => 24.0, 'normal_max' => 35.9, 'high_max' => 41.9],
        ],
        'male' => [
            ['min_age' => 20, 'max_age' => 39, 'normal_min' => 8.0, 'normal_max' => 19.9, 'high_max' => 24.9],
            ['min_age' => 40, 'max_age' => 59, 'normal_min' => 11.0, 'normal_max' => 21.9, 'high_max' => 27.9],
            ['min_age' => 60, 'max_age' => 79, 'normal_min' => 13.0, 'normal_max' => 24.9, 'high_max' => 29.9],
        ],
    ];

    private const SKELETAL_MUSCLE_RANGES = [
        'female' => [
            ['min_age' => 18, 'max_age' => 39, 'normal_min' => 24.3, 'normal_max' => 30.3, 'high_max' => 35.3],
            ['min_age' => 40, 'max_age' => 59, 'normal_min' => 24.1, 'normal_max' => 30.1, 'high_max' => 35.1],
            ['min_age' => 60, 'max_age' => 80, 'normal_min' => 23.9, 'normal_max' => 29.9, 'high_max' => 34.9],
        ],
        'male' => [
            ['min_age' => 18, 'max_age' => 39, 'normal_min' => 33.3, 'normal_max' => 39.3, 'high_max' => 44.0],
            ['min_age' => 40, 'max_age' => 59, 'normal_min' => 33.1, 'normal_max' => 39.1, 'high_max' => 43.8],
            ['min_age' => 60, 'max_age' => 80, 'normal_min' => 32.9, 'normal_max' => 38.9, 'high_max' => 43.6],
        ],
    ];

    public function analyze(array $client, array $assessment): array
    {
        $heightCm = (float) ($assessment['height_cm_at_assessment'] ?? $client['height_cm']);
        $heightM = $heightCm / 100;
        $weightKg = (float) $assessment['weight_kg'];
        $calculatedBmi = round($weightKg / ($heightM * $heightM), 1);
        $scaleBmi = isset($assessment['scale_bmi']) ? (float) $assessment['scale_bmi'] : null;
        $bmiDifference = $scaleBmi === null ? null : round($scaleBmi - $calculatedBmi, 2);
        $age = $this->ageAtAssessment($client, $assessment);
        $sex = $assessment['biological_sex_at_assessment'] ?? $client['biological_sex'];

        return [
            'source' => 'Omron HBF-514C',
            'reference' => $this->reference(),
            'age' => $age,
            'snapshot' => [
                'age_at_assessment' => $age,
                'height_cm_at_assessment' => $heightCm,
                'biological_sex_at_assessment' => $sex,
                'device_model' => self::DEVICE_MODEL,
                'reference_version' => self::REFERENCE_VERSION,
            ],
            'calculated_bmi' => $calculatedBmi,
            'bmi_difference' => $bmiDifference,
            'summary' => $this->summary($calculatedBmi, $assessment, $age, $sex),
            'indicators' => $this->indicators($calculatedBmi, $assessment, $age, $sex),
            'warnings' => $this->warnings($client, $assessment, $calculatedBmi, $bmiDifference),
        ];
    }

    private function summary(float $calculatedBmi, array $assessment, int $age, string $sex): string
    {
        $bmi = $this->bmiClassification($calculatedBmi);
        $fat = isset($assessment['body_fat_percentage'])
            ? 'Gordura corporal registrada em '.number_format((float) $assessment['body_fat_percentage'], 1, ',', '.').'%, classificada como '.$this->bodyFatClassification((float) $assessment['body_fat_percentage'], $sex, $age)['classification'].'.'
            : 'Gordura corporal nao informada.';

        return "IMC calculado automaticamente com base em peso e altura: {$calculatedBmi} kg/m², classificado como {$bmi['classification']}. {$fat}";
    }

    private function indicators(float $calculatedBmi, array $assessment, int $age, string $sex): array
    {
        return [
            'weight' => [
                'label' => 'Peso',
                'value' => number_format((float) $assessment['weight_kg'], 1, ',', '.').' kg',
                'classification' => null,
                'tone' => 'neutral',
            ],
            'bmi' => [
                'label' => 'IMC',
                'value' => number_format($calculatedBmi, 1, ',', '.').' kg/m²',
                ...$this->bmiClassification($calculatedBmi),
            ],
            'body_fat' => $this->bodyFatIndicator($assessment['body_fat_percentage'] ?? null, $sex, $age),
            'skeletal_muscle' => $this->skeletalMuscleIndicator($assessment['skeletal_muscle_percentage'] ?? null, $sex, $age),
            'resting_metabolism' => [
                'label' => 'Metabolismo basal',
                'value' => isset($assessment['resting_metabolism_kcal'])
                    ? number_format((int) $assessment['resting_metabolism_kcal'], 0, ',', '.').' kcal'
                    : 'Nao informado',
                'classification' => null,
                'tone' => 'neutral',
            ],
            'body_age' => [
                'label' => 'Idade corporal',
                'value' => isset($assessment['body_age']) ? ((int) $assessment['body_age']).' anos' : 'Nao informada',
                'classification' => null,
                'tone' => 'neutral',
            ],
            'visceral_fat' => $this->visceralFatIndicator($assessment['visceral_fat_level'] ?? null, $age),
        ];
    }

    private function bmiClassification(float $bmi): array
    {
        return match (true) {
            $bmi < 18.5 => ['classification' => 'Baixo peso', 'tone' => 'attention'],
            $bmi < 25 => ['classification' => 'Eutrofia', 'tone' => 'good'],
            $bmi < 30 => ['classification' => 'Sobrepeso', 'tone' => 'warning'],
            $bmi < 35 => ['classification' => 'Obesidade grau I', 'tone' => 'danger'],
            $bmi < 40 => ['classification' => 'Obesidade grau II', 'tone' => 'danger'],
            default => ['classification' => 'Obesidade grau III', 'tone' => 'danger'],
        };
    }

    private function bodyFatIndicator(mixed $value, string $sex, int $age): array
    {
        $range = $this->ageRange($sex, $age, self::BODY_FAT_RANGES);

        return [
            'label' => 'Gordura corporal',
            'value' => $this->formattedDecimal($value, '%'),
            ...$this->bodyFatClassification($value, $sex, $age),
            'scale' => $this->ageRangeScale((float) $value, 5, 60, $range, ['Baixa', 'Normal', 'Elevada', 'Muito elevada']),
        ];
    }

    private function skeletalMuscleIndicator(mixed $value, string $sex, int $age): array
    {
        $range = $this->ageRange($sex, $age, self::SKELETAL_MUSCLE_RANGES);

        return [
            'label' => 'Musculo esqueletico',
            'value' => $this->formattedDecimal($value, '%'),
            ...$this->skeletalMuscleClassification($value, $sex, $age),
            'scale' => $this->ageRangeScale((float) $value, 5, 50, $range, ['Baixo', 'Normal', 'Alto', 'Muito alto']),
        ];
    }

    private function visceralFatIndicator(mixed $value, int $age): array
    {
        return [
            'label' => 'Gordura visceral',
            'value' => $this->formattedDecimal($value, ''),
            ...$this->visceralFatClassification($value, $age),
            'scale' => $this->thresholdScale((float) $value, 1, 30, [10, 15], ['Normal', 'Elevada', 'Muito elevada'], ['bg-emerald-500', 'bg-amber-500', 'bg-rose-500']),
        ];
    }

    private function bodyFatClassification(mixed $value, string $sex, int $age): array
    {
        return $this->ageBasedClassification($value, $sex, $age, self::BODY_FAT_RANGES, [
            'low' => 'Baixa',
            'normal' => 'Normal',
            'high' => 'Elevada',
            'very_high' => 'Muito elevada',
        ]);
    }

    private function skeletalMuscleClassification(mixed $value, string $sex, int $age): array
    {
        return $this->ageBasedClassification($value, $sex, $age, self::SKELETAL_MUSCLE_RANGES, [
            'low' => 'Baixo',
            'normal' => 'Normal',
            'high' => 'Alto',
            'very_high' => 'Muito alto',
        ]);
    }

    private function visceralFatClassification(mixed $value, int $age): array
    {
        if ($value === null || $value === '') {
            return $this->pendingClassification('Nao informado');
        }

        if ($age < 18 || $age > 80) {
            return $this->pendingClassification('Fora da faixa etaria Omron');
        }

        $value = (float) $value;

        return match (true) {
            $value < 10 => ['classification' => 'Normal', 'tone' => 'good', 'pending' => false],
            $value < 15 => ['classification' => 'Elevada', 'tone' => 'warning', 'pending' => false],
            default => ['classification' => 'Muito elevada', 'tone' => 'danger', 'pending' => false],
        };
    }

    private function ageBasedClassification(mixed $value, string $sex, int $age, array $ranges, array $labels): array
    {
        if ($value === null || $value === '') {
            return $this->pendingClassification('Nao informado');
        }

        $range = $this->ageRange($sex, $age, $ranges);

        if (! $range) {
            return $this->pendingClassification('Fora da faixa etaria Omron');
        }

        $value = (float) $value;

        return match (true) {
            $value < $range['normal_min'] => ['classification' => $labels['low'], 'tone' => 'attention', 'pending' => false],
            $value <= $range['normal_max'] => ['classification' => $labels['normal'], 'tone' => 'good', 'pending' => false],
            $value <= $range['high_max'] => ['classification' => $labels['high'], 'tone' => 'warning', 'pending' => false],
            default => ['classification' => $labels['very_high'], 'tone' => 'danger', 'pending' => false],
        };
    }

    private function ageRange(string $sex, int $age, array $ranges): ?array
    {
        return collect($ranges[$sex] ?? [])
            ->first(fn (array $range) => $age >= $range['min_age'] && $age <= $range['max_age']);
    }

    private function ageRangeScale(float $value, float $min, float $max, ?array $range, array $labels): array
    {
        if (! $range) {
            return $this->thresholdScale($value, $min, $max, [], $labels);
        }

        return $this->thresholdScale($value, $min, $max, [
            $range['normal_min'],
            $range['normal_max'] + 0.1,
            $range['high_max'] + 0.1,
        ], $labels);
    }

    private function thresholdScale(float $value, float $min, float $max, array $thresholds, array $labels, array $colors = ['bg-blue-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500']): array
    {
        $points = [$min, ...$thresholds, $max];
        $segments = [];

        for ($index = 0; $index < count($points) - 1; $index++) {
            $segments[] = [
                'className' => $colors[$index] ?? 'bg-slate-300',
                'width' => (($points[$index + 1] - $points[$index]) / ($max - $min)) * 100,
                'label' => $labels[$index] ?? null,
            ];
        }

        return [
            'position' => max(2, min(98, (($value - $min) / ($max - $min)) * 100)),
            'labels' => $labels,
            'segments' => $segments,
        ];
    }

    private function formattedDecimal(mixed $value, string $suffix): string
    {
        if ($value === null || $value === '') {
            return 'Nao informado';
        }

        return number_format((float) $value, 1, ',', '.').($suffix ? " {$suffix}" : '');
    }

    private function pendingClassification(string $classification): array
    {
        return [
            'classification' => $classification,
            'tone' => 'pending',
            'pending' => true,
        ];
    }

    private function ageAtAssessment(array $client, array $assessment): int
    {
        if (isset($assessment['age_at_assessment']) && $assessment['age_at_assessment'] !== null) {
            return (int) $assessment['age_at_assessment'];
        }

        $birthDate = CarbonImmutable::parse($client['birth_date']);
        $evaluatedAt = CarbonImmutable::parse($assessment['evaluated_at'] ?? now());

        return (int) $birthDate->diffInYears($evaluatedAt);
    }

    private function reference(): array
    {
        return [
            'manufacturer' => 'Omron',
            'model' => self::DEVICE_MODEL,
            'manual_code' => '5344832-6B',
            'version' => 'LA IM SP r2',
            'classification_version' => self::REFERENCE_VERSION,
        ];
    }

    private function warnings(array $client, array $assessment, float $calculatedBmi, ?float $bmiDifference): array
    {
        $warnings = [];

        $heightCm = (float) ($assessment['height_cm_at_assessment'] ?? $client['height_cm']);

        if ($heightCm < 100 || $heightCm > 199.5) {
            $warnings[] = 'Altura fora da faixa aceita pela Omron HBF-514C. Confira o cadastro do cliente.';
        }

        if ((float) $assessment['weight_kg'] < 2 || (float) $assessment['weight_kg'] > 150) {
            $warnings[] = 'Peso fora da faixa aceita pela Omron HBF-514C. Confira o valor digitado a partir da balanca.';
        }

        if ($calculatedBmi < 7 || $calculatedBmi > 90) {
            $warnings[] = 'IMC calculado muito fora do esperado. Verifique peso e altura.';
        }

        if ($bmiDifference !== null && abs($bmiDifference) >= 0.5) {
            $warnings[] = 'O IMC informado pela balanca difere do IMC calculado em '.number_format(abs($bmiDifference), 1, ',', '.').' ponto(s).';
        }

        foreach ([
            'body_fat_percentage' => 'gordura corporal',
            'skeletal_muscle_percentage' => 'musculo esqueletico',
        ] as $field => $label) {
            if (isset($assessment[$field]) && ((float) $assessment[$field] < 5 || (float) $assessment[$field] > ($field === 'body_fat_percentage' ? 60 : 50))) {
                $warnings[] = "Percentual de {$label} fora da faixa aceita pela Omron HBF-514C. Confira a digitacao.";
            }
        }

        return $warnings;
    }
}
