<?php

namespace Database\Seeders;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\User;
use App\Services\Bioimpedance\BioimpedanceAnalyzer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BioimpedanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $professional = User::query()->where('email', 'milicofelix@gmail.com')->first()
            ?? User::query()->first();
        $analyzer = app(BioimpedanceAnalyzer::class);

        foreach ($this->clients() as $clientData) {
            $assessments = $clientData['assessments'];
            unset($clientData['assessments']);
            $clientData['phone_digits'] = $this->digits($clientData['phone'] ?? null);

            $client = BioimpedanceClient::query()->updateOrCreate([
                'email' => $clientData['email'],
            ], $clientData);

            $client->assessments()->delete();

            foreach ($assessments as $assessmentData) {
                $snapshot = $this->snapshot($clientData, $assessmentData['evaluated_at']);
                $calculatedBmi = $this->bmi($assessmentData['weight_kg'], $clientData['height_cm']);
                $payload = [
                    ...$assessmentData,
                    ...$snapshot,
                    'bioimpedance_client_id' => $client->id,
                    'user_id' => $professional?->id,
                    'calculated_bmi' => $calculatedBmi,
                    'bmi_difference' => round(($assessmentData['scale_bmi'] ?? $calculatedBmi) - $calculatedBmi, 2),
                ];

                BioimpedanceAssessment::query()->create([
                    ...$payload,
                    'analysis' => $analyzer->analyze($client->toArray(), $payload),
                ]);
            }
        }
    }

    private function bmi(float $weightKg, float $heightCm): float
    {
        $heightM = $heightCm / 100;

        return round($weightKg / ($heightM * $heightM), 1);
    }

    private function digits(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    private function snapshot(array $clientData, string $evaluatedAt): array
    {
        return [
            'age_at_assessment' => (int) CarbonImmutable::parse($clientData['birth_date'])->diffInYears(CarbonImmutable::parse($evaluatedAt)),
            'height_cm_at_assessment' => $clientData['height_cm'],
            'biological_sex_at_assessment' => $clientData['biological_sex'],
            'device_model' => BioimpedanceAnalyzer::DEVICE_MODEL,
            'reference_version' => BioimpedanceAnalyzer::REFERENCE_VERSION,
        ];
    }

    private function clients(): array
    {
        return [
            [
                'full_name' => 'Ana Carolina Mendes',
                'birth_date' => '1989-04-12',
                'biological_sex' => 'female',
                'height_cm' => 164,
                'phone' => '(11) 98001-1001',
                'email' => 'demo.ana.mendes@ricosty.local',
                'notes' => 'Demo - evolução consistente de composição corporal.',
                'assessments' => $this->series('2026-01-08', 82.4, 30.6, 38.5, 25.8, 1610, 49, 12, [
                    [-1.4, -1.1, -1.8, 0.7, -18, -1, -1],
                    [-1.2, -1.0, -1.6, 0.6, -15, -1, -1],
                    [-1.5, -1.1, -1.7, 0.8, -20, -1, -1],
                    [-1.1, -0.8, -1.3, 0.5, -12, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Bruno Henrique Lima',
                'birth_date' => '1984-09-22',
                'biological_sex' => 'male',
                'height_cm' => 178,
                'phone' => '(11) 98001-1002',
                'email' => 'demo.bruno.lima@ricosty.local',
                'notes' => 'Demo - regressão no meio do acompanhamento.',
                'assessments' => $this->series('2026-01-15', 96.0, 30.3, 24.8, 35.2, 2050, 48, 13, [
                    [0.8, 0.3, 1.0, -0.3, 18, 1, 1],
                    [1.4, 0.5, 1.5, -0.5, 25, 2, 1],
                    [0.9, 0.3, 1.0, -0.4, 10, 1, 1],
                    [-0.6, -0.2, -0.4, 0.2, -8, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Camila Rocha Alves',
                'birth_date' => '1995-02-18',
                'biological_sex' => 'female',
                'height_cm' => 158,
                'phone' => '(11) 98001-1003',
                'email' => 'demo.camila.alves@ricosty.local',
                'notes' => 'Demo - evolução leve com platô.',
                'assessments' => $this->series('2026-02-03', 67.8, 27.2, 33.2, 27.0, 1390, 39, 8, [
                    [-0.7, -0.3, -0.8, 0.2, -6, 0, 0],
                    [-0.2, -0.1, -0.1, 0.1, -2, 0, 0],
                    [-0.1, 0.0, 0.2, 0.0, 0, 0, 0],
                    [-0.5, -0.2, -0.6, 0.2, -5, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Daniel Santos Pereira',
                'birth_date' => '1978-12-05',
                'biological_sex' => 'male',
                'height_cm' => 172,
                'phone' => '(11) 98001-1004',
                'email' => 'demo.daniel.pereira@ricosty.local',
                'notes' => 'Demo - estabilidade geral.',
                'assessments' => $this->series('2026-02-19', 88.7, 30.0, 22.4, 36.4, 1880, 53, 11, [
                    [0.1, 0.1, -0.1, 0.0, 3, 0, 0],
                    [-0.2, -0.1, 0.0, 0.1, -2, 0, 0],
                    [0.2, 0.1, 0.2, -0.1, 2, 0, 0],
                    [-0.1, 0.0, -0.1, 0.0, -1, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Elisa Fernanda Costa',
                'birth_date' => '1968-06-30',
                'biological_sex' => 'female',
                'height_cm' => 160,
                'phone' => '(11) 98001-1005',
                'email' => 'demo.elisa.costa@ricosty.local',
                'notes' => 'Demo - melhora importante de gordura visceral.',
                'assessments' => $this->series('2026-03-04', 78.2, 30.5, 41.2, 24.5, 1490, 64, 16, [
                    [-1.0, -0.4, -1.4, 0.3, -10, -1, -1],
                    [-0.9, -0.3, -1.2, 0.3, -8, -1, -1],
                    [-0.8, -0.3, -1.0, 0.4, -8, -1, -1],
                    [-0.6, -0.2, -0.8, 0.2, -5, 0, -1],
                ]),
            ],
            [
                'full_name' => 'Felipe Augusto Nogueira',
                'birth_date' => '1991-11-08',
                'biological_sex' => 'male',
                'height_cm' => 181,
                'phone' => '(11) 98001-1006',
                'email' => 'demo.felipe.nogueira@ricosty.local',
                'notes' => 'Demo - ganho muscular com redução de gordura.',
                'assessments' => $this->series('2026-03-21', 84.5, 25.8, 21.5, 37.0, 1990, 38, 9, [
                    [-0.4, -0.1, -1.0, 0.8, 12, -1, 0],
                    [-0.5, -0.2, -0.9, 0.7, 14, -1, 0],
                    [0.1, 0.0, -0.7, 0.8, 16, -1, -1],
                    [-0.2, -0.1, -0.5, 0.5, 10, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Gabriela Martins Souza',
                'birth_date' => '1982-01-25',
                'biological_sex' => 'female',
                'height_cm' => 169,
                'phone' => '(11) 98001-1007',
                'email' => 'demo.gabriela.souza@ricosty.local',
                'notes' => 'Demo - regressão por aumento de peso e gordura.',
                'assessments' => $this->series('2026-04-02', 72.1, 25.2, 32.8, 28.6, 1505, 43, 9, [
                    [0.9, 0.3, 1.2, -0.3, 14, 1, 1],
                    [1.1, 0.4, 1.5, -0.4, 18, 1, 1],
                    [0.6, 0.2, 0.8, -0.2, 6, 0, 0],
                    [0.4, 0.1, 0.6, -0.1, 4, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Henrique Oliveira Barros',
                'birth_date' => '1974-08-14',
                'biological_sex' => 'male',
                'height_cm' => 176,
                'phone' => '(11) 98001-1008',
                'email' => 'demo.henrique.barros@ricosty.local',
                'notes' => 'Demo - oscilação com melhora final.',
                'assessments' => $this->series('2026-04-18', 101.3, 32.7, 29.0, 34.2, 2105, 58, 17, [
                    [-1.2, -0.4, -0.9, 0.2, -10, 0, 0],
                    [0.5, 0.2, 0.7, -0.2, 8, 1, 1],
                    [-1.8, -0.6, -1.7, 0.6, -20, -2, -2],
                    [-1.0, -0.3, -1.0, 0.4, -10, -1, -1],
                ]),
            ],
            [
                'full_name' => 'Isabela Dias Ferreira',
                'birth_date' => '2000-05-16',
                'biological_sex' => 'female',
                'height_cm' => 162,
                'phone' => '(11) 98001-1009',
                'email' => 'demo.isabela.ferreira@ricosty.local',
                'notes' => 'Demo - cliente jovem com manutenção.',
                'assessments' => $this->series('2026-05-07', 61.2, 23.3, 27.6, 29.1, 1360, 27, 6, [
                    [-0.2, -0.1, -0.2, 0.1, -2, 0, 0],
                    [0.0, 0.0, 0.1, 0.0, 1, 0, 0],
                    [-0.3, -0.1, -0.3, 0.2, -3, 0, 0],
                    [0.1, 0.0, 0.0, 0.0, 0, 0, 0],
                ]),
            ],
            [
                'full_name' => 'João Victor Ribeiro',
                'birth_date' => '1987-03-02',
                'biological_sex' => 'male',
                'height_cm' => 170,
                'phone' => '(11) 98001-1010',
                'email' => 'demo.joao.ribeiro@ricosty.local',
                'notes' => 'Demo - aumento de gordura visceral apesar de peso quase estável.',
                'assessments' => $this->series('2026-05-24', 79.0, 27.3, 20.2, 38.0, 1850, 41, 8, [
                    [0.2, 0.1, 0.8, -0.2, 5, 0, 1],
                    [0.3, 0.1, 0.9, -0.3, 5, 1, 1],
                    [-0.1, 0.0, 0.5, -0.1, 1, 0, 1],
                    [0.1, 0.0, 0.4, -0.1, 1, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Karen Beatriz Lopes',
                'birth_date' => '1979-10-19',
                'biological_sex' => 'female',
                'height_cm' => 155,
                'phone' => '(11) 98001-1011',
                'email' => 'demo.karen.lopes@ricosty.local',
                'notes' => 'Demo - grande evolução com perda de peso.',
                'assessments' => $this->series('2026-06-05', 86.0, 35.8, 42.0, 23.8, 1515, 58, 15, [
                    [-2.0, -0.8, -2.2, 0.5, -18, -1, -1],
                    [-1.8, -0.8, -2.0, 0.6, -16, -1, -1],
                    [-1.5, -0.6, -1.8, 0.5, -12, -1, -1],
                    [-1.2, -0.5, -1.5, 0.4, -10, -1, -1],
                ]),
            ],
            [
                'full_name' => 'Lucas Matheus Almeida',
                'birth_date' => '1998-07-11',
                'biological_sex' => 'male',
                'height_cm' => 183,
                'phone' => '(11) 98001-1012',
                'email' => 'demo.lucas.almeida@ricosty.local',
                'notes' => 'Demo - recomposição: peso sobe, gordura cai e músculo aumenta.',
                'assessments' => $this->series('2026-06-22', 76.5, 22.8, 17.8, 39.0, 1920, 28, 6, [
                    [0.8, 0.2, -0.6, 0.9, 22, 0, 0],
                    [0.7, 0.2, -0.5, 0.8, 20, 0, 0],
                    [0.4, 0.1, -0.4, 0.7, 16, 0, 0],
                    [0.3, 0.1, -0.3, 0.5, 10, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Mariana Pires Gomes',
                'birth_date' => '1992-09-09',
                'biological_sex' => 'female',
                'height_cm' => 167,
                'phone' => '(11) 98001-1013',
                'email' => 'demo.mariana.gomes@ricosty.local',
                'notes' => 'Demo - sem evolução relevante.',
                'assessments' => $this->series('2026-07-03', 69.4, 24.9, 31.0, 28.8, 1455, 36, 8, [
                    [0.0, 0.0, 0.1, -0.1, 2, 0, 0],
                    [0.2, 0.1, 0.0, 0.0, 0, 0, 0],
                    [-0.1, 0.0, -0.1, 0.1, -1, 0, 0],
                    [0.1, 0.0, 0.1, -0.1, 1, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Nelson Carlos Moreira',
                'birth_date' => '1965-02-01',
                'biological_sex' => 'male',
                'height_cm' => 168,
                'phone' => '(11) 98001-1014',
                'email' => 'demo.nelson.moreira@ricosty.local',
                'notes' => 'Demo - melhora lenta em cliente acima de 60 anos.',
                'assessments' => $this->series('2026-07-19', 91.2, 32.3, 28.4, 32.9, 1760, 67, 16, [
                    [-0.8, -0.3, -0.6, 0.2, -6, 0, 0],
                    [-0.7, -0.2, -0.5, 0.2, -5, 0, -1],
                    [-0.5, -0.2, -0.4, 0.1, -4, 0, 0],
                    [-0.4, -0.1, -0.3, 0.1, -3, 0, 0],
                ]),
            ],
            [
                'full_name' => 'Patrícia Helena Ramos',
                'birth_date' => '1986-12-27',
                'biological_sex' => 'female',
                'height_cm' => 171,
                'phone' => '(11) 98001-1015',
                'email' => 'demo.patricia.ramos@ricosty.local',
                'notes' => 'Demo - regressão após início promissor.',
                'assessments' => $this->series('2026-08-02', 74.6, 25.5, 34.1, 27.5, 1510, 42, 9, [
                    [-0.6, -0.2, -0.5, 0.2, -5, 0, 0],
                    [0.9, 0.3, 1.2, -0.3, 13, 1, 1],
                    [1.1, 0.4, 1.4, -0.4, 16, 1, 1],
                    [0.5, 0.2, 0.8, -0.2, 6, 0, 0],
                ]),
            ],
        ];
    }

    private function series(
        string $startDate,
        float $weightKg,
        float $scaleBmi,
        float $bodyFatPercentage,
        float $skeletalMusclePercentage,
        int $restingMetabolismKcal,
        int $bodyAge,
        float $visceralFatLevel,
        array $changes
    ): array {
        $date = Carbon::parse($startDate)->setTime(9, 30);
        $current = [
            'weight_kg' => $weightKg,
            'scale_bmi' => $scaleBmi,
            'body_fat_percentage' => $bodyFatPercentage,
            'skeletal_muscle_percentage' => $skeletalMusclePercentage,
            'resting_metabolism_kcal' => $restingMetabolismKcal,
            'body_age' => $bodyAge,
            'visceral_fat_level' => $visceralFatLevel,
        ];

        $series = [$this->assessment($date, $current, 'Avaliação inicial demonstrativa.')];

        foreach ($changes as $index => [$weight, $bmi, $fat, $muscle, $metabolism, $age, $visceral]) {
            $date = $date->copy()->addWeeks(4);
            $current = [
                'weight_kg' => round($current['weight_kg'] + $weight, 1),
                'scale_bmi' => round($current['scale_bmi'] + $bmi, 1),
                'body_fat_percentage' => round($current['body_fat_percentage'] + $fat, 1),
                'skeletal_muscle_percentage' => round($current['skeletal_muscle_percentage'] + $muscle, 1),
                'resting_metabolism_kcal' => $current['resting_metabolism_kcal'] + $metabolism,
                'body_age' => $current['body_age'] + $age,
                'visceral_fat_level' => round($current['visceral_fat_level'] + $visceral, 1),
            ];

            $series[] = $this->assessment($date, $current, 'Avaliação demonstrativa '.($index + 2).'.');
        }

        return $series;
    }

    private function assessment(Carbon $date, array $values, string $notes): array
    {
        return [
            ...$values,
            'evaluated_at' => $date->toDateTimeString(),
            'notes' => $notes,
        ];
    }
}
