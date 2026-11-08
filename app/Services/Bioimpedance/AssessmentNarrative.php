<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement as Segment;

class AssessmentNarrative
{
    public function classificationOverview(array $findings, string $type): string
    {
        $groups = collect($findings)->where('kind', 'segmental')->where('metric', $type)
            ->groupBy(fn ($finding) => $finding['classification'] ?? 'unknown');
        if ($groups->isEmpty()) {
            return '';
        }
        $text = 'Na análise segmentar de '.($type === Segment::TYPE_FAT ? 'gordura' : 'massa muscular').', as estimativas foram classificadas como '
            .$groups->map(fn ($group, $classification) => (Segment::CLASSIFICATION_LABELS[$classification] ?? 'Não identificada')
                .($group->count() === 5 ? ' nas cinco regiões avaliadas' : ' em '.$group->pluck('region_label')->filter()->implode(', ')))->implode('; ').'.';
        if (collect($findings)->where('metric', $type)->contains('classification_origin', 'recorded_unverified')) {
            $text .= ' Há classificações cuja origem precisa ser conferida.';
        }

        return $text;
    }

    public function segmentPoints(array $context): array
    {
        return collect($context['findings'] ?? [])->where('kind', 'segmental')
            ->groupBy(fn (array $finding) => implode('|', [$finding['metric'], $finding['classification'], $finding['classification_origin']]))
            ->map(function ($group): array {
                $first = $group->first();
                $type = $first['metric'] === Segment::TYPE_FAT ? 'gordura' : 'massa muscular';
                $regions = $group->pluck('region_label')->filter()->implode(', ');
                $origin = match ($first['classification_origin']) {
                    'equipment_report' => 'no relatório de origem',
                    'equipment_reference_range' => 'pela faixa registrada do equipamento',
                    'professional_review' => 'na revisão profissional',
                    default => 'com origem não verificada',
                };
                $description = match ($first['classification']) {
                    Segment::CLASSIFICATION_HIGH => 'acima das referências do equipamento',
                    Segment::CLASSIFICATION_LOW => 'abaixo das referências do equipamento',
                    Segment::CLASSIFICATION_STANDARD => 'dentro das referências do equipamento',
                    default => 'sem classificação identificada',
                };
                $scope = $group->count() === 5 ? 'nas cinco regiões avaliadas' : 'em '.$regions;
                $text = 'A análise segmentar apresentou estimativas de '.$type.' '.$description.' '.$scope
                    .' (classificação '.(Segment::CLASSIFICATION_LABELS[$first['classification']] ?? 'Não identificada').' '.$origin.').';
                $percentages = $group->filter(fn ($finding) => is_numeric($finding['reference_percentage'] ?? null));
                if ($first['metric'] === Segment::TYPE_FAT && $percentages->isNotEmpty()) {
                    $largest = $percentages->sortByDesc('reference_percentage')->first();
                    $text .= ' O maior percentual relativo à referência nesse grupo foi registrado em '.mb_strtolower($largest['region_label']).' ('.number_format($largest['reference_percentage'], 1, ',', '.').'%).';
                }

                return ['text' => $text, 'source_chunk_ids' => $group->pluck('id')->all()];
            })->values()->all();
    }

    public function compactSegments(array $context): string
    {
        $findings = $context['findings'] ?? [];
        $parts = [$this->classificationOverview($findings, 'fat'), $this->classificationOverview($findings, 'muscle')];
        $fat = collect($findings)->where('kind', 'segmental')->where('metric', 'fat')
            ->filter(fn ($finding) => is_numeric($finding['reference_percentage'] ?? null))->sortByDesc('reference_percentage')->first();
        if ($fat) {
            $parts[] = 'O maior percentual relativo à referência da gordura segmentar foi registrado em '.mb_strtolower($fat['region_label']).' ('.number_format($fat['reference_percentage'], 1, ',', '.').'%).';
        }

        return implode(' ', array_filter($parts));
    }

    public function composition(array $context): string
    {
        $metrics = collect($context['general_metrics'] ?? [])->keyBy('code');
        $bmi = $metrics->get('calculated_bmi');
        $fat = $metrics->get('body_fat_percentage');
        $parts = [];
        if ($bmi) {
            $parts[] = 'O IMC calculado foi de '.$this->number($bmi['value']).' kg/m²'
                .(filled($bmi['classification'] ?? null) ? ', classificado como '.$bmi['classification'] : ', sem classificação disponível').'.';
        }
        if ($fat) {
            $parts[] = 'A gordura corporal foi estimada em '.$this->number($fat['value']).'%'
                .(filled($fat['classification'] ?? null) ? ', com classificação '.$fat['classification'].' pela referência informada' : ', sem classificação textual disponível no contexto').'.';
        }
        if ($bmi || $fat) {
            $parts[] = 'Esses indicadores descrevem aspectos diferentes da composição corporal e devem ser contextualizados em conjunto, não usados isoladamente para definir metas.';
        }

        return implode(' ', $parts);
    }

    private function number(mixed $value): string
    {
        return number_format((float) $value, 1, ',', '.');
    }
}
