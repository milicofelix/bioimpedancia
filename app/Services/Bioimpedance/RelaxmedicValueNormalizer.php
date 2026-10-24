<?php

namespace App\Services\Bioimpedance;

use Illuminate\Support\Str;

class RelaxmedicValueNormalizer
{
    private const OBESITY_LEVELS = [
        'baixo peso' => 'Baixo peso',
        'abaixo do peso' => 'Baixo peso',
        'peso baixo' => 'Baixo peso',
        'underweight' => 'Baixo peso',
        'normal' => 'Normal',
        'padrao' => 'Normal',
        'peso normal' => 'Normal',
        'saudavel' => 'Normal',
        'peso saudavel' => 'Normal',
        'standard' => 'Normal',
        'healthy' => 'Normal',
        'sobrepeso' => 'Sobrepeso',
        'excesso de peso' => 'Sobrepeso',
        'overweight' => 'Sobrepeso',
        'pre obesidade' => 'Pré-obesidade',
        'preobesidade' => 'Pré-obesidade',
        'pre obeso' => 'Pré-obesidade',
        'pre obesity' => 'Pré-obesidade',
        'alto' => 'Alto',
        'high' => 'Alto',
        'obesidade' => 'Obesidade',
        'obesity' => 'Obesidade',
        'obesidade grau i' => 'Obesidade grau I',
        'obesidade grau 1' => 'Obesidade grau I',
        'eu nivel a obesidade' => 'Obesidade grau I',
        'i nivel a obesidade' => 'Obesidade grau I',
        '1 nivel a obesidade' => 'Obesidade grau I',
        'nivel i de obesidade' => 'Obesidade grau I',
        'nivel 1 de obesidade' => 'Obesidade grau I',
        'obesidade nivel i' => 'Obesidade grau I',
        'obesidade nivel 1' => 'Obesidade grau I',
        'obesity level i' => 'Obesidade grau I',
        'obesity level 1' => 'Obesidade grau I',
        'obesidade grau ii' => 'Obesidade grau II',
        'obesidade grau 2' => 'Obesidade grau II',
        'ii nivel a obesidade' => 'Obesidade grau II',
        '2 nivel a obesidade' => 'Obesidade grau II',
        'nivel ii de obesidade' => 'Obesidade grau II',
        'nivel 2 de obesidade' => 'Obesidade grau II',
        'obesidade nivel ii' => 'Obesidade grau II',
        'obesidade nivel 2' => 'Obesidade grau II',
        'obesity level ii' => 'Obesidade grau II',
        'obesity level 2' => 'Obesidade grau II',
        'obesidade grau iii' => 'Obesidade grau III',
        'obesidade grau 3' => 'Obesidade grau III',
        'iii nivel a obesidade' => 'Obesidade grau III',
        '3 nivel a obesidade' => 'Obesidade grau III',
        'nivel iii de obesidade' => 'Obesidade grau III',
        'nivel 3 de obesidade' => 'Obesidade grau III',
        'obesidade nivel iii' => 'Obesidade grau III',
        'obesidade nivel 3' => 'Obesidade grau III',
        'obesity level iii' => 'Obesidade grau III',
        'obesity level 3' => 'Obesidade grau III',
    ];

    public function obesityLevel(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = $this->key($value);

        return self::OBESITY_LEVELS[$normalized] ?? null;
    }

    public function recognizesObesityLevel(mixed $value): bool
    {
        return is_string($value) && array_key_exists($this->key($value), self::OBESITY_LEVELS);
    }

    public function obesityLevelOptions(): array
    {
        return array_values(array_unique(self::OBESITY_LEVELS));
    }

    private function key(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }
}
