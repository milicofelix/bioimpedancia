<?php

namespace App\Services\Bioimpedance;

use Illuminate\Support\Str;

class RelaxmedicValueNormalizer
{
    private const OBESITY_LEVELS = [
        'normal' => 'Normal',
        'padrao' => 'Normal',
        'sobrepeso' => 'Sobrepeso',
        'alto' => 'Alto',
        'eu nivel a obesidade' => 'Obesidade grau I',
        'i nivel a obesidade' => 'Obesidade grau I',
        'nivel i de obesidade' => 'Obesidade grau I',
        'obesidade nivel i' => 'Obesidade grau I',
        'ii nivel a obesidade' => 'Obesidade grau II',
        'nivel ii de obesidade' => 'Obesidade grau II',
        'obesidade nivel ii' => 'Obesidade grau II',
        'iii nivel a obesidade' => 'Obesidade grau III',
        'nivel iii de obesidade' => 'Obesidade grau III',
        'obesidade nivel iii' => 'Obesidade grau III',
    ];

    public function obesityLevel(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = $this->key($value);

        return self::OBESITY_LEVELS[$normalized] ?? mb_substr(trim($value), 0, 100);
    }

    public function recognizesObesityLevel(mixed $value): bool
    {
        return is_string($value) && array_key_exists($this->key($value), self::OBESITY_LEVELS);
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
