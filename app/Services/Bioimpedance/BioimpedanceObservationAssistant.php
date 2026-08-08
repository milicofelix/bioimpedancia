<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use Illuminate\Support\Collection;

class BioimpedanceObservationAssistant
{
    private const REFERENCES = [
        'Classificações oficiais armazenadas na avaliação, geradas pelas regras determinísticas da Omron HBF-514C.',
        'IMC calculado pelo sistema com base em peso e altura do snapshot da avaliação.',
        'Interpretação textual voltada a acompanhamento, sem substituir avaliação médica, nutricional ou conduta individualizada.',
    ];

    public function suggest(BioimpedanceAssessment $assessment): array
    {
        $assessment->loadMissing(['client', 'professional']);
        $history = $this->historyBefore($assessment);
        $previous = $history->last();
        $analysis = $assessment->analysis ?? [];
        $indicators = $analysis['indicators'] ?? [];
        $age = $assessment->age_at_assessment;
        $bodyAgeDelta = $assessment->body_age !== null && $age !== null
            ? $assessment->body_age - $age
            : null;

        return [
            'suggestion' => $this->composeSuggestion($assessment, $indicators, $previous, $bodyAgeDelta),
            'context' => [
                'client_name' => $assessment->client?->full_name,
                'age_at_assessment' => $age,
                'biological_sex_at_assessment' => $assessment->biological_sex_at_assessment,
                'device_model' => $assessment->device_model,
                'reference_version' => $assessment->reference_version,
                'previous_assessments_count' => $history->count(),
                'warnings' => $analysis['warnings'] ?? [],
            ],
            'references' => self::REFERENCES,
            'notice' => 'Sugestão gerada para revisão do profissional. As classificações oficiais não foram alteradas.',
        ];
    }

    private function composeSuggestion(BioimpedanceAssessment $assessment, array $indicators, ?BioimpedanceAssessment $previous, ?int $bodyAgeDelta): string
    {
        $parts = [
            $this->classificationSentence($assessment, $indicators),
            $this->bodyAgeSentence($assessment, $bodyAgeDelta),
            $this->variationSentence($assessment, $previous),
            $this->warningSentence($assessment),
            'Recomenda-se acompanhamento periódico da evolução corporal, mantendo condições semelhantes entre as medições e avaliação individualizada com profissional habilitado para definição de condutas.',
        ];

        return collect($parts)
            ->filter()
            ->implode(' ');
    }

    private function classificationSentence(BioimpedanceAssessment $assessment, array $indicators): string
    {
        $bmi = $this->classification($indicators, 'bmi');
        $bodyFat = $this->classification($indicators, 'body_fat');
        $muscle = $this->classification($indicators, 'skeletal_muscle');
        $visceral = $this->classification($indicators, 'visceral_fat');
        $segments = [];

        if ($bodyFat) {
            $segments[] = 'gordura corporal '.$bodyFat;
        }

        if ($muscle) {
            $segments[] = 'músculo esquelético '.$muscle;
        }

        if ($visceral) {
            $segments[] = 'gordura visceral '.$visceral;
        }

        $base = $segments
            ? 'A avaliação apresentou '.implode(', ', $segments).', conforme as referências da Omron HBF-514C para sexo e idade.'
            : 'A avaliação foi registrada conforme os indicadores disponíveis da Omron HBF-514C.';

        if ($bmi) {
            $base .= ' O IMC calculado foi de '.$this->number($assessment->calculated_bmi, 1).' kg/m², classificado como '.$bmi.'.';
        }

        return $base;
    }

    private function bodyAgeSentence(BioimpedanceAssessment $assessment, ?int $bodyAgeDelta): ?string
    {
        if ($assessment->body_age === null || $bodyAgeDelta === null) {
            return null;
        }

        if ($bodyAgeDelta === 0) {
            return 'A idade corporal estimada ficou equivalente à idade cronológica.';
        }

        return 'A idade corporal estimada foi de '.$assessment->body_age.' anos, ficando '.abs($bodyAgeDelta).' anos '.($bodyAgeDelta > 0 ? 'acima' : 'abaixo').' da idade cronológica.';
    }

    private function variationSentence(BioimpedanceAssessment $assessment, ?BioimpedanceAssessment $previous): ?string
    {
        if (! $previous) {
            return 'Esta avaliação servirá como linha de base para comparação nas próximas medições.';
        }

        $changes = collect([
            $this->changeText('peso', $previous->weight_kg, $assessment->weight_kg, 'kg', true),
            $this->changeText('gordura corporal', $previous->body_fat_percentage, $assessment->body_fat_percentage, 'ponto percentual', true),
            $this->changeText('músculo esquelético', $previous->skeletal_muscle_percentage, $assessment->skeletal_muscle_percentage, 'ponto percentual', false),
            $this->changeText('gordura visceral', $previous->visceral_fat_level, $assessment->visceral_fat_level, 'nível', true, 0),
        ])->filter();

        if ($changes->isEmpty()) {
            return null;
        }

        return 'Em relação à avaliação anterior, houve '.$changes->implode(', ').'.';
    }

    private function warningSentence(BioimpedanceAssessment $assessment): ?string
    {
        $warnings = $assessment->analysis['warnings'] ?? [];

        if (! count($warnings)) {
            return null;
        }

        return 'Há alerta de consistência registrado: '.$warnings[0];
    }

    private function changeText(string $label, mixed $previousValue, mixed $currentValue, string $unit, bool $lowerIsAttention, int $decimals = 1): ?string
    {
        if ($previousValue === null || $currentValue === null) {
            return null;
        }

        $delta = round((float) $currentValue - (float) $previousValue, $decimals);

        if ($delta == 0.0) {
            return 'manutenção de '.$label;
        }

        $direction = $delta > 0 ? 'aumento' : 'redução';
        $attention = ($lowerIsAttention && $delta > 0) || (! $lowerIsAttention && $delta < 0);
        $suffix = $attention ? ', ponto de atenção no acompanhamento' : '';

        return $direction.' de '.$this->number(abs($delta), $decimals).' '.$unit.($unit === 'nível' && abs($delta) != 1.0 ? 's' : '').' em '.$label.$suffix;
    }

    private function classification(array $indicators, string $key): ?string
    {
        $classification = $indicators[$key]['classification'] ?? null;

        if (! $classification || ($indicators[$key]['pending'] ?? false)) {
            return null;
        }

        return mb_strtolower($classification);
    }

    private function historyBefore(BioimpedanceAssessment $assessment): Collection
    {
        return BioimpedanceAssessment::query()
            ->where('bioimpedance_client_id', $assessment->bioimpedance_client_id)
            ->whereNull('canceled_at')
            ->where('evaluated_at', '<', $assessment->evaluated_at)
            ->orderBy('evaluated_at')
            ->get();
    }

    private function number(mixed $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}
