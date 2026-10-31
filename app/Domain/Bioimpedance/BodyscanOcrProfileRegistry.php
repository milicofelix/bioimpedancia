<?php

namespace App\Domain\Bioimpedance;

use InvalidArgumentException;

final class BodyscanOcrProfileRegistry
{
    public const METRIC_FIELDS = [
        'weight_kg',
        'scale_bmi',
        'body_fat_percentage',
        'muscle_rate_percentage',
        'lean_body_mass_kg',
        'subcutaneous_fat_percentage',
        'visceral_fat_level',
        'body_water_percentage',
        'skeletal_muscle_percentage',
        'muscle_mass_kg',
        'bone_mass_kg',
        'protein_percentage',
        'resting_metabolism_kcal',
        'body_age',
        'fat_mass_kg',
        'water_weight_kg',
        'protein_mass_kg',
        'ideal_body_weight_kg',
        'obesity_level',
        'body_type',
    ];

    /** @return list<BodyscanOcrProfile> */
    public function all(): array
    {
        return [
            $this->fitdaysTechnical(),
            $this->fitdaysVertical(),
            $this->relaxfitVertical(),
        ];
    }

    public function forLayout(string $layout): BodyscanOcrProfile
    {
        foreach ($this->all() as $profile) {
            if ($profile->layout === $layout) {
                return $profile;
            }
        }

        throw new InvalidArgumentException('Perfil OCR Bodyscan não reconhecido.');
    }

    public function promptBlocks(): string
    {
        return collect($this->all())->map(fn (BodyscanOcrProfile $profile) => $profile->promptBlock())->implode("\n\n");
    }

    private function fitdaysTechnical(): BodyscanOcrProfile
    {
        return new BodyscanOcrProfile(
            layout: BodyscanReportLayout::FITDAYS_TECHNICAL,
            application: ScaleModel::BODYSCAN_APPLICATION_FITDAYS,
            version: BodyscanReportLayout::profileVersion(BodyscanReportLayout::FITDAYS_TECHNICAL),
            extractionMode: 'table_rows_and_columns',
            expectedTitles: ['Relatório de análise de composição corporal', 'Pontuação corporal', 'Impedância bioelétrica'],
            metricAliases: $this->fitdaysAliases(),
            requiredSections: [
                'body_composition' => ['Análise da composição corporal'],
                'segmental_fat' => ['Análise de gordura segmentar'],
                'muscle_balance' => ['Equilíbrio muscular'],
                'bioelectrical_impedance' => ['Impedância bioelétrica'],
            ],
            availableMetricFields: $this->fitdaysTechnicalMetricFields(),
            integrityRules: [
                'Leia a tabela de composição por linha e associe Medição, Proporção de peso e Avaliação ao rótulo da mesma linha.',
                'Não use a coluna Proporção de peso como massa em kg.',
                'Confirme que a tabela segmentar contém L e R legíveis nos braços e pernas.',
                'Nos diagramas técnicos, leia cada bloco na ordem massa em kg, percentual relativo e classificação; não espelhe L/R pela perspectiva anatômica.',
                'A referência de gordura segmentar impressa é 80%-160%; no equilíbrio muscular é 80%-115% para braços e 90%-110% para tronco e pernas.',
                'A tabela de impedância precisa estar visível para reconhecer este perfil, mas será estruturada em fase própria.',
            ],
        );
    }

    private function fitdaysVertical(): BodyscanOcrProfile
    {
        return new BodyscanOcrProfile(
            layout: BodyscanReportLayout::FITDAYS_VERTICAL,
            application: ScaleModel::BODYSCAN_APPLICATION_FITDAYS,
            version: BodyscanReportLayout::profileVersion(BodyscanReportLayout::FITDAYS_VERTICAL),
            extractionMode: 'label_value_pairs',
            expectedTitles: ['Indicador', 'Valor', 'Padrão', 'Limb Dados', 'Controle de peso'],
            metricAliases: $this->fitdaysAliases(),
            requiredSections: [
                'general_metrics' => ['Indicador / Valor / Padrão', 'Indicador', 'Valor', 'Padrão'],
                'segmental_container' => ['Limb Dados'],
                'segmental_fat' => ['Análise de gordura segmentar'],
                'muscle_balance' => ['Equilíbrio muscular'],
            ],
            availableMetricFields: $this->fitdaysVerticalMetricFields(),
            integrityRules: [
                'Extraia cada métrica pelo rótulo textual e pelo valor exibido na mesma linha.',
                'Não use a classificação da coluna Padrão como valor da métrica.',
                'Confirme L e R diretamente nos dois diagramas segmentares.',
                'A ausência de impedância bioelétrica é esperada neste layout e não representa falha de leitura.',
            ],
        );
    }

    private function relaxfitVertical(): BodyscanOcrProfile
    {
        return new BodyscanOcrProfile(
            layout: BodyscanReportLayout::RELAXFIT_VERTICAL,
            application: ScaleModel::BODYSCAN_APPLICATION_RELAXFIT,
            version: BodyscanReportLayout::profileVersion(BodyscanReportLayout::RELAXFIT_VERTICAL),
            extractionMode: 'label_value_pairs',
            expectedTitles: ['Dados de membro', 'Análise de gordura segmentar', 'Equilíbrio muscular', 'Índice corporal'],
            metricAliases: $this->relaxfitAliases(),
            requiredSections: [
                'member_data' => ['Dados de membro'],
                'segmental_fat' => ['Análise de gordura segmentar'],
                'muscle_balance' => ['Equilíbrio muscular'],
                'body_index' => ['Índice corporal'],
            ],
            availableMetricFields: self::METRIC_FIELDS,
            integrityRules: [
                'Extraia cada métrica pelo rótulo exibido no bloco Índice corporal.',
                'Confirme L e R diretamente nos dois diagramas segmentares.',
                'Não deduza valores usando cores, ícones ou a posição da linha.',
            ],
        );
    }

    /** @return list<string> */
    private function fitdaysTechnicalMetricFields(): array
    {
        return array_values(array_diff(self::METRIC_FIELDS, ['ideal_body_weight_kg', 'obesity_level', 'body_type']));
    }

    /** @return list<string> */
    private function fitdaysVerticalMetricFields(): array
    {
        return array_values(array_diff(self::METRIC_FIELDS, ['obesity_level', 'body_type']));
    }

    /** @return array<string, list<string>> */
    private function fitdaysAliases(): array
    {
        return [
            'weight_kg' => ['Peso'],
            'scale_bmi' => ['IMC'],
            'body_fat_percentage' => ['Gordura corporal', 'Taxa de gordura corporal', 'Massa gorda'],
            'muscle_rate_percentage' => ['Taxa muscular', 'Massa muscular', 'Proporção de peso da Massa muscular'],
            'lean_body_mass_kg' => ['Massa Livre de Gordura', 'Massa corporal magra', 'Peso corporal livre de gordura'],
            'subcutaneous_fat_percentage' => ['Gordura subcutânea'],
            'visceral_fat_level' => ['Gordura visceral', 'Grau de gordura visceral'],
            'body_water_percentage' => ['Água corporal', 'Proporção de peso da Água corporal'],
            'skeletal_muscle_percentage' => ['Massa Musc. Esquelética', 'Músculo esquelético', 'Proporção de peso do Músculo esquelético'],
            'muscle_mass_kg' => ['Massa Muscular', 'Massa muscular'],
            'bone_mass_kg' => ['Massa Óssea', 'Massa óssea'],
            'protein_percentage' => ['Proteína', 'Massa proteica', 'Proporção de peso da Massa proteica'],
            'resting_metabolism_kcal' => ['TMB', 'Taxa metabólica basal'],
            'body_age' => ['Idade Metabólica', 'Idade do corpo'],
            'fat_mass_kg' => ['Massa gorda'],
            'water_weight_kg' => ['Teor de umidade', 'Água corporal', 'Medição da Água corporal'],
            'protein_mass_kg' => ['Massa proteica'],
            'ideal_body_weight_kg' => ['Peso corporal ideal'],
        ];
    }

    /** @return array<string, list<string>> */
    private function relaxfitAliases(): array
    {
        return [
            ...$this->fitdaysAliases(),
            'obesity_level' => ['Nível de obesidade'],
            'body_type' => ['Tipo de corpo'],
        ];
    }
}
