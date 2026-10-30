<?php

namespace App\Domain\Bioimpedance;

use Illuminate\Support\Str;

final class BodyscanExtendedMetricCatalog
{
    public const WHR = 'waist_hip_ratio';

    public const BODY_SCORE = 'body_score';

    public const RECOMMENDED_TARGET_WEIGHT = 'recommended_target_weight';

    public const WEIGHT_CONTROL = 'weight_control';

    public const FAT_CONTROL = 'fat_control';

    public const MUSCLE_CONTROL = 'muscle_control';

    public const PROPRIETARY_OBESITY_INDEX = 'proprietary_obesity_index';

    public const ASMI = 'asmi';

    public const BODY_BALANCE_ASSESSMENT = 'body_balance_assessment';

    public const CONTEXTUAL_GENERAL_CODES = [
        'weight',
        'bmi',
        'body_fat_percentage',
        'fat_mass',
        'bone_mass',
        'protein_mass',
        'body_water',
        'muscle_mass',
        'muscle_rate',
        'skeletal_muscle',
        'subcutaneous_fat',
        'visceral_fat',
    ];

    public const CODES = [
        self::WHR,
        self::BODY_SCORE,
        self::RECOMMENDED_TARGET_WEIGHT,
        self::WEIGHT_CONTROL,
        self::FAT_CONTROL,
        self::MUSCLE_CONTROL,
        self::PROPRIETARY_OBESITY_INDEX,
        self::ASMI,
        self::BODY_BALANCE_ASSESSMENT,
        ...self::CONTEXTUAL_GENERAL_CODES,
    ];

    public const REFERENCE_VERSION = 'fitdays-proprietary-v1';

    public const LABELS = [
        self::WHR => 'Relação cintura-quadril (WHR)',
        self::BODY_SCORE => 'Pontuação corporal',
        self::RECOMMENDED_TARGET_WEIGHT => 'Peso-alvo recomendado',
        self::WEIGHT_CONTROL => 'Controle de peso',
        self::FAT_CONTROL => 'Controle de gordura',
        self::MUSCLE_CONTROL => 'Controle muscular',
        self::PROPRIETARY_OBESITY_INDEX => 'Índice proprietário de obesidade',
        self::ASMI => 'ASMI',
        self::BODY_BALANCE_ASSESSMENT => 'Avaliação do equilíbrio corporal',
        'weight' => 'Peso',
        'bmi' => 'IMC',
        'body_fat_percentage' => 'Gordura corporal',
        'fat_mass' => 'Massa gorda',
        'bone_mass' => 'Massa óssea',
        'protein_mass' => 'Massa proteica',
        'body_water' => 'Água corporal',
        'muscle_mass' => 'Massa muscular',
        'muscle_rate' => 'Taxa muscular',
        'skeletal_muscle' => 'Músculo esquelético',
        'subcutaneous_fat' => 'Gordura subcutânea',
        'visceral_fat' => 'Gordura visceral',
    ];

    public static function isTextual(string $code): bool
    {
        return $code === self::BODY_BALANCE_ASSESSMENT;
    }

    public static function recognizesLabel(string $code, string $label): bool
    {
        $normalizedLabel = Str::lower(Str::ascii(trim($label)));
        $aliases = match ($code) {
            self::WHR => ['whr'],
            self::BODY_SCORE => ['pontuacao corporal'],
            self::RECOMMENDED_TARGET_WEIGHT => ['peso-alvo recomendado', 'peso alvo recomendado'],
            self::WEIGHT_CONTROL => ['controle de peso'],
            self::FAT_CONTROL => ['controle de gordura'],
            self::MUSCLE_CONTROL => ['controle muscular'],
            self::PROPRIETARY_OBESITY_INDEX => ['obesidade (peso atual/peso alvo)', 'obesidade'],
            self::ASMI => ['asmi'],
            self::BODY_BALANCE_ASSESSMENT => ['avaliacao do equilibrio corporal'],
            'weight' => ['peso'],
            'bmi' => ['imc'],
            'body_fat_percentage' => ['gordura corporal', 'taxa de gordura corporal'],
            'fat_mass' => ['massa gorda'],
            'bone_mass' => ['massa ossea'],
            'protein_mass' => ['massa proteica', 'proteina'],
            'body_water' => ['agua corporal', 'teor de umidade'],
            'muscle_mass' => ['massa muscular'],
            'muscle_rate' => ['taxa muscular'],
            'skeletal_muscle' => ['musculo esqueletico', 'massa musc. esqueletica'],
            'subcutaneous_fat' => ['gordura subcutanea'],
            'visceral_fat' => ['gordura visceral', 'grau de gordura visceral'],
            default => [],
        };

        return collect($aliases)->contains(fn (string $alias) => str_contains($normalizedLabel, $alias));
    }

    /** @return list<string> */
    public static function availableForLayout(string $layout): array
    {
        return match ($layout) {
            BodyscanReportLayout::FITDAYS_TECHNICAL => self::CODES,
            BodyscanReportLayout::FITDAYS_VERTICAL => [
                self::WHR,
                self::RECOMMENDED_TARGET_WEIGHT,
                self::WEIGHT_CONTROL,
                self::FAT_CONTROL,
                self::MUSCLE_CONTROL,
                ...self::CONTEXTUAL_GENERAL_CODES,
            ],
            default => [],
        };
    }

    /** @return list<string> */
    public static function requiredForLayout(string $layout): array
    {
        return match ($layout) {
            BodyscanReportLayout::FITDAYS_TECHNICAL => [
                self::WHR,
                self::BODY_SCORE,
                self::RECOMMENDED_TARGET_WEIGHT,
                self::WEIGHT_CONTROL,
                self::FAT_CONTROL,
                self::MUSCLE_CONTROL,
                self::PROPRIETARY_OBESITY_INDEX,
                self::ASMI,
                self::BODY_BALANCE_ASSESSMENT,
            ],
            BodyscanReportLayout::FITDAYS_VERTICAL => [
                self::WHR,
                self::RECOMMENDED_TARGET_WEIGHT,
                self::WEIGHT_CONTROL,
                self::FAT_CONTROL,
                self::MUSCLE_CONTROL,
            ],
            default => [],
        };
    }
}
