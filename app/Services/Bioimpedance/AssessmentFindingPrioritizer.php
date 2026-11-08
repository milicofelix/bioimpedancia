<?php

namespace App\Services\Bioimpedance;

class AssessmentFindingPrioritizer
{
    public const POLICY = 'Achados para revisão antes dos contextuais; classificações com origem verificada antes das não verificadas. Nos segmentos do mesmo tipo e classificação, ordenar pelo afastamento da faixa registrada; sem faixa, pelo percentual de referência (decrescente para Alto, crescente para Baixo). Não comparar tipos diferentes como risco clínico, não recalcular classificações e não descartar regiões.';

    public function ordered(array $findings): array
    {
        return collect($findings)->sort(function (array $first, array $second): int {
            return $this->key($first) <=> $this->key($second);
        })->values()->all();
    }

    public function attention(array $findings): array
    {
        return array_values(array_filter($this->ordered($findings), fn (array $finding) => ($finding['priority'] ?? null) === 'review'));
    }

    private function key(array $finding): array
    {
        $value = $finding['reference_percentage'] ?? null;
        $low = $finding['reference_min_percentage'] ?? null;
        $high = $finding['reference_max_percentage'] ?? null;
        $distance = is_numeric($value) && is_numeric($low) && is_numeric($high)
            ? max(0, $low - $value, $value - $high)
            : 0;

        return [
            ($finding['priority'] ?? null) === 'review' ? 0 : 1,
            ($finding['classification_origin'] ?? null) === 'recorded_unverified' ? 1 : 0,
            match ($finding['kind'] ?? null) { 'measurement' => 0, 'segmental' => 1, default => 2 },
            $finding['metric'] ?? '',
            $finding['classification'] ?? '',
            -$distance,
            is_numeric($value) ? (($finding['classification'] ?? null) === 'low' ? $value : -$value) : 0,
            $finding['id'],
        ];
    }
}
