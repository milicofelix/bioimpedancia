<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanExtendedMetricCatalog;
use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement as Segment;

class AssessmentFindings
{
    public function __construct(private readonly AssessmentFindingPrioritizer $prioritizer = new AssessmentFindingPrioritizer) {}

    public function from(array $context): array
    {
        $findings = [];
        foreach ($context['general_metrics'] ?? [] as $metric) {
            if (! is_numeric($metric['value'] ?? null)) {
                continue;
            }
            $findings[] = [
                'id' => $metric['evidence_id'],
                'kind' => 'measurement',
                'metric' => $metric['code'],
                'value' => $metric['value'],
                'unit' => $metric['unit'],
                'label' => $metric['label'],
                'priority' => in_array($metric['tone'] ?? null, ['warning', 'danger', 'attention'], true) ? 'review' : 'context',
                'classification_origin' => $metric['classification_origin'] ?? null,
                'classification' => $metric['classification'] ?? null,
                'text' => $metric['label'].': '.$this->number($metric['value']).' '.$metric['unit']
                    .(empty($metric['classification']) ? '' : '; classificação '.$metric['classification'].' pela referência do sistema').'.',
                'source_ids' => [$metric['evidence_id']],
                'limitations' => ['Valor registrado; não permite inferir diagnóstico, hábitos ou capacidade funcional.'],
            ];
        }
        foreach (data_get($context, 'segmental_analysis.current', []) as $segment) {
            $classification = $segment['device_classification'] ?? null;
            if (! in_array($classification, Segment::CLASSIFICATIONS, true)) {
                $classification = null;
            }
            $region = match ([$segment['region'], $segment['side']]) {
                ['arm', 'left'] => 'braço esquerdo', ['arm', 'right'] => 'braço direito',
                ['leg', 'left'] => 'perna esquerda', ['leg', 'right'] => 'perna direita',
                ['trunk', 'center'] => 'tronco', default => null,
            };
            if ($region === null) {
                continue;
            }
            $label = $segment['type'] === Segment::TYPE_FAT ? 'Gordura segmentar' : 'Equilíbrio muscular';
            $origin = $segment['classification_origin'] ?? 'recorded_unverified';
            $originLabel = match ($origin) {
                'equipment_report' => 'no relatório de origem',
                'equipment_reference_range' => 'derivada da faixa de referência do relatório',
                'professional_review' => 'confirmada na revisão profissional',
                default => 'registrada na avaliação, sem origem verificada',
            };
            $findings[] = [
                'id' => $segment['evidence_id'],
                'kind' => 'segmental',
                'metric' => $segment['type'],
                'region' => $segment['region'],
                'side' => $segment['side'],
                'region_label' => $region,
                'reference_percentage' => $segment['reference_percentage'],
                'reference_min_percentage' => $segment['reference_min_percentage'] ?? null,
                'reference_max_percentage' => $segment['reference_max_percentage'] ?? null,
                'priority' => $classification === null || $classification === Segment::CLASSIFICATION_STANDARD || $origin === 'recorded_unverified' ? 'context' : 'review',
                'classification_origin' => $origin,
                'classification' => $classification,
                'text' => $label.' — '.$region.': '.($classification === null ? 'sem classificação identificada' : 'classificação '.Segment::CLASSIFICATION_LABELS[$classification]).' '.$originLabel
                    .($segment['reference_percentage'] === null ? '' : ' ('.$this->number($segment['reference_percentage']).'% da referência)').'.',
                'source_ids' => [$segment['evidence_id']],
                'limitations' => ['Classificação do equipamento, não clínica; não comprova força, função ou assimetria.'],
            ];
        }

        foreach ($context['extended_indices'] ?? [] as $index) {
            if (! is_numeric($index['value'] ?? null)) {
                continue;
            }
            $findings[] = [
                'id' => $index['evidence_id'], 'kind' => 'equipment_index', 'metric' => $index['code'],
                'priority' => 'context', 'classification_origin' => null, 'classification' => null,
                'text' => $index['label'].': '.$this->number((float) $index['value'], $index['code'] === BodyscanExtendedMetricCatalog::WHR ? 2 : 1).' '.($index['unit'] ?? '').'. Índice estimado pelo equipamento, com interpretação isolada limitada.',
                'source_ids' => [$index['evidence_id']],
                'limitations' => ['Não sustenta diagnóstico ou definição de conduta isoladamente.'],
            ];
        }

        return $this->prioritizer->ordered($findings);
    }

    public function fingerprint(array $context): string
    {
        return hash('sha256', json_encode([
            $context['measurement_profile'] ?? [],
            $context['general_metrics'] ?? [],
            $context['extended_indices'] ?? [],
            // Additional editorial metadata must not invalidate existing approvals.
            collect(data_get($context, 'segmental_analysis.current', []))
                ->map(fn (array $segment) => \Illuminate\Support\Arr::except($segment, ['reference_min_percentage', 'reference_max_percentage']))->all(),
            data_get($context, 'impedance.readings', []),
            $context['history_comparison'] ?? [],
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function number(float|int $value, int $decimals = 1): string
    {
        return number_format($value, $decimals, ',', '.');
    }
}
