<?php

namespace App\Services\Bioimpedance;

use Carbon\CarbonImmutable;

class BioimpedanceAnalyzer
{
    public function analyze(array $client, array $assessment): array
    {
        $heightM = ((float) $client['height_cm']) / 100;
        $weightKg = (float) $assessment['weight_kg'];
        $calculatedBmi = round($weightKg / ($heightM * $heightM), 1);
        $scaleBmi = isset($assessment['scale_bmi']) ? (float) $assessment['scale_bmi'] : null;
        $bmiDifference = $scaleBmi === null ? null : round($scaleBmi - $calculatedBmi, 2);

        return [
            'age' => CarbonImmutable::parse($client['birth_date'])->age,
            'calculated_bmi' => $calculatedBmi,
            'bmi_difference' => $bmiDifference,
            'summary' => $this->summary($calculatedBmi, $assessment),
            'indicators' => $this->indicators($calculatedBmi, $assessment),
            'warnings' => $this->warnings($client, $assessment, $calculatedBmi, $bmiDifference),
        ];
    }

    private function summary(float $calculatedBmi, array $assessment): string
    {
        $bmi = $this->bmiClassification($calculatedBmi);
        $fat = isset($assessment['body_fat_percentage'])
            ? 'Gordura corporal registrada em '.number_format((float) $assessment['body_fat_percentage'], 1, ',', '.').'%.'
            : 'Gordura corporal nao informada.';

        return "IMC calculado em {$calculatedBmi} kg/m2, classificado como {$bmi['classification']}. {$fat}";
    }

    private function indicators(float $calculatedBmi, array $assessment): array
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
                'value' => number_format($calculatedBmi, 1, ',', '.').' kg/m2',
                ...$this->bmiClassification($calculatedBmi),
            ],
            'body_fat' => $this->pendingOmronIndicator('Gordura corporal', $assessment['body_fat_percentage'] ?? null, '%'),
            'skeletal_muscle' => $this->pendingOmronIndicator('Musculo esqueletico', $assessment['skeletal_muscle_percentage'] ?? null, '%'),
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
            'visceral_fat' => $this->pendingOmronIndicator('Gordura visceral', $assessment['visceral_fat_level'] ?? null, ''),
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

    private function pendingOmronIndicator(string $label, mixed $value, string $suffix): array
    {
        $formattedValue = $value === null
            ? 'Nao informado'
            : number_format((float) $value, 1, ',', '.').($suffix ? " {$suffix}" : '');

        return [
            'label' => $label,
            'value' => $formattedValue,
            'classification' => 'Aguardando manual Omron',
            'tone' => 'pending',
        ];
    }

    private function warnings(array $client, array $assessment, float $calculatedBmi, ?float $bmiDifference): array
    {
        $warnings = [];

        if ((float) $client['height_cm'] < 100 || (float) $client['height_cm'] > 230) {
            $warnings[] = 'Altura fora da faixa esperada para adultos. Confira o cadastro do cliente.';
        }

        if ((float) $assessment['weight_kg'] < 25 || (float) $assessment['weight_kg'] > 250) {
            $warnings[] = 'Peso fora da faixa esperada. Confira o valor digitado a partir da balanca.';
        }

        if ($calculatedBmi < 10 || $calculatedBmi > 80) {
            $warnings[] = 'IMC calculado muito fora do esperado. Verifique peso e altura.';
        }

        if ($bmiDifference !== null && abs($bmiDifference) >= 0.5) {
            $warnings[] = 'O IMC informado pela balanca difere do IMC calculado em '.number_format(abs($bmiDifference), 1, ',', '.').' ponto(s).';
        }

        foreach ([
            'body_fat_percentage' => 'gordura corporal',
            'skeletal_muscle_percentage' => 'musculo esqueletico',
        ] as $field => $label) {
            if (isset($assessment[$field]) && ((float) $assessment[$field] < 1 || (float) $assessment[$field] > 80)) {
                $warnings[] = "Percentual de {$label} parece incomum. Confira a digitacao.";
            }
        }

        return $warnings;
    }
}
