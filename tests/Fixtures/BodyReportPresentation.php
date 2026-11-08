<?php

namespace Tests\Fixtures;

use App\Services\Bioimpedance\AssessmentNarrative;
use App\Services\Bioimpedance\AssessmentFindingPrioritizer;
use App\Services\Bioimpedance\BioimpedanceCareGuidanceAssistant;
use App\Services\Bioimpedance\SegmentalBodySvgRenderer;

class BodyReportPresentation
{
    public static function data(bool $maleAthlete, bool $historical): array
    {
        $context = BodyCompositionProfiles::context($maleAthlete);
        $narrative = new AssessmentNarrative;
        $notes = $narrative->composition($context).' '.collect($narrative->segmentPoints($context))->pluck('text')->implode(' ')
            .' Os percentuais são relativos à referência do equipamento, não gordura regional. Este é um registro inicial para acompanhamento profissional.';
        if ($historical) {
            $notes = str_repeat('Os valores de gordura, massa corporal magra, água e segmentos são estimativas do equipamento, sem comprovar força ou função. ', 9)
                .'Este registro é uma linha de base para acompanhamento em condições semelhantes de medição.';
        }
        $context['prioritized_findings'] = (new AssessmentFindingPrioritizer)->attention($context['findings']);
        $context['attention_points'] = array_values(array_filter([
            ...collect($context['prioritized_findings'])->where('kind', '!=', 'segmental')->pluck('text')->all(),
            $narrative->classificationOverview($context['prioritized_findings'], 'fat'),
            $narrative->classificationOverview($context['prioritized_findings'], 'muscle'),
        ]));
        $assistant = app(BioimpedanceCareGuidanceAssistant::class);
        $payload = (new \ReflectionMethod($assistant, 'localResponse'))->invoke($assistant, $context);
        $guidance = (new \ReflectionMethod($assistant, 'renderGuidance'))->invoke($assistant, $payload['claims']);
        if ($historical) {
            $guidance = 'Contexto da avaliação: '.str_repeat('Os registros devem ser contextualizados pelo profissional; diferenças entre regiões não comprovam assimetria clínica e estimativas musculares não avaliam força nem função. ', 4)
                ."\n\nAlimentação: A classificação da gordura registrada nesta avaliação não é uma indicação de dieta. Para a revisão com o profissional, observe a composição habitual das refeições e a presença de ultraprocessados; o Guia Alimentar oferece opções com alimentos in natura e minimamente processados, sem definir quantidades individuais."
                ."\n\nMovimento e função: A balança não informa seu nível de atividade ou condicionamento. Converse sobre sua rotina e atividades de que gosta antes de escolher possibilidades de movimento; qualquer progressão depende da capacidade individual e da avaliação profissional."
                ."\n\nAcompanhamento: Use esta avaliação como registro inicial. Na revisão, relate objetivos e condições da medição e combine com o profissional como acompanhar os resultados, sem adotar o peso-alvo do equipamento como meta pessoal.";
        }
        $metrics = collect($context['general_metrics'])->keyBy('code');
        $segments = array_map(fn ($segment) => [...$segment, 'classification' => $segment['device_classification']], $context['segmental_analysis']['current']);
        $extended = collect([
            ['recommended_target_weight', 'Peso-alvo estimado pelo equipamento', $maleAthlete ? 79.9 : 57.2, 'kg'],
            ['weight_control', 'Controle de peso', $maleAthlete ? -12.2 : -17.9, 'kg'],
            ['fat_control', 'Controle de gordura', $maleAthlete ? -12.6 : -17.9, 'kg'],
            ['muscle_control', 'Controle muscular', $maleAthlete ? 2.9 : 0, 'kg'],
            ['waist_hip_ratio', 'WHR', $maleAthlete ? .95 : .88, ''],
            ['asmi', 'ASMI', $maleAthlete ? 10.4 : 6.8, 'kg/m²'],
        ])->map(fn ($metric) => [
            'metric_code' => $metric[0], 'original_label' => $metric[1], 'numeric_value' => $metric[2], 'unit' => $metric[3],
            'application_classification' => null, 'printed_minimum' => null, 'printed_maximum' => null,
            'source_application' => 'fitdays', 'weight_proportion_percentage' => null,
        ])->all();
        $readings = [];
        foreach ([20, 100] as $frequency) {
            foreach ([['arm', 'left'], ['arm', 'right'], ['trunk', 'center'], ['leg', 'left'], ['leg', 'right']] as [$region, $side]) {
                $readings[] = ['frequency_khz' => $frequency, 'region' => $region, 'side' => $side, 'impedance_ohms' => 200, 'reading_state' => 'read'];
            }
        }
        return [
            'client' => ['id' => 999, 'full_name' => $maleAthlete ? 'Perfil de regressão A' : 'Perfil de regressão B', 'age' => $maleAthlete ? 45 : 54, 'height_cm' => $maleAthlete ? 174 : 165, 'biological_sex' => $maleAthlete ? 'male' : 'female'],
            'clinic' => [
                'display_name' => 'Vita Scan', 'contact' => 'Ambiente de teste', 'logo_data_uri' => null,
                'technical_notice' => 'Os resultados de bioimpedância são estimativas e podem variar conforme hidratação, alimentação, ciclo hormonal, medicamentos e condições de medição. Este documento não substitui avaliação médica ou nutricional.',
            ],
            'assessment' => [
                'id' => 999, 'is_canceled' => false, 'evaluated_at' => '2026-11-03T12:17:00+00:00',
                'device_model' => 'Bodyscan Pro', 'device_model_code' => 'RM-BD0015A', 'reference_version' => '1.1.0',
                'measurement_application' => 'fitdays', 'athlete_mode_at_assessment' => $maleAthlete,
                'weight_kg' => $metrics['weight_kg']['value'], 'calculated_bmi' => $metrics['calculated_bmi']['value'],
                'body_fat_percentage' => $metrics['body_fat_percentage']['value'], 'skeletal_muscle_percentage' => $metrics['skeletal_muscle_percentage']['value'],
                'visceral_fat_level' => $maleAthlete ? 10 : 13, 'resting_metabolism_kcal' => $maleAthlete ? 1862 : 1320,
                'body_age' => $maleAthlete ? 40 : 57, 'notes' => $notes, 'care_guidance' => $guidance,
                'care_guidance_approved_at' => '2026-11-07T15:43:00+00:00', 'care_guidance_approved_by_name' => 'Profissional de teste',
                'observation_origin' => 'ai_approved', 'professional_name' => 'Profissional de teste',
                'analysis' => [
                    'summary' => $narrative->composition($context),
                    'indicators' => ['bmi' => ['classification' => $metrics['calculated_bmi']['classification']], 'body_fat' => ['pending' => true], 'skeletal_muscle' => ['pending' => true], 'visceral_fat' => ['pending' => true]],
                ],
                'segmental_measurements' => $segments, 'extended_metrics' => $extended, 'impedance_readings' => $readings,
            ],
            'segmentalBodyImages' => (new SegmentalBodySvgRenderer)->renderPair($segments),
            'comparison' => [], 'issuedAt' => now(),
        ];
    }
}
