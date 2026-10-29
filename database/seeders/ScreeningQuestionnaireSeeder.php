<?php

namespace Database\Seeders;

use App\Domain\Screening\VitaAceaQuestionnaire;
use App\Models\Screening\ScreeningQuestionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScreeningQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $definition = VitaAceaQuestionnaire::definition();

            $questionnaire = ScreeningQuestionnaire::query()->firstOrCreate([
                'code' => $definition['code'],
                'version' => $definition['version'],
            ], [
                'name' => $definition['title'],
                'description' => 'Triagem descritiva informal sobre frequência de sinais relacionados à ansiedade e ao comportamento alimentar.',
                'status' => ScreeningQuestionnaire::STATUS_PENDING_PROFESSIONAL_APPROVAL,
                'recall_period_days' => $definition['recall_period_days'],
                'consent_text' => 'Declaro que compreendi a finalidade descritiva desta triagem, autorizo o registro das minhas respostas e sei que o resultado não constitui diagnóstico.',
                'disclaimer' => $definition['disclaimer'],
                'professional_review_notice' => $definition['professional_review_notice'],
                'reference_sources' => [
                    [
                        'title' => 'VitaScan — Questionário de Triagem — Ansiedade e Comportamento Alimentar',
                        'type' => 'editorial_source',
                        'validation_status' => 'not_clinically_validated',
                    ],
                    [
                        'title' => 'Sistema de Avaliação de Testes Psicológicos — SATEPSI',
                        'url' => 'https://satepsi.cfp.org.br/',
                        'type' => 'professional_guidance',
                    ],
                ],
                'approved_by_user_id' => null,
                'approved_by_name' => null,
                'professional_registration' => null,
                'approved_at' => null,
            ]);

            if (! $questionnaire->wasRecentlyCreated) {
                $definitionIsLocked = $questionnaire->status !== ScreeningQuestionnaire::STATUS_PENDING_PROFESSIONAL_APPROVAL
                    || $questionnaire->sessions()->exists();

                if ($definitionIsLocked) {
                    return;
                }

                $questionnaire->update([
                    'name' => $definition['title'],
                    'description' => 'Triagem descritiva informal sobre frequência de sinais relacionados à ansiedade e ao comportamento alimentar.',
                    'recall_period_days' => $definition['recall_period_days'],
                    'consent_text' => 'Declaro que compreendi a finalidade descritiva desta triagem, autorizo o registro das minhas respostas e sei que o resultado não constitui diagnóstico.',
                    'disclaimer' => $definition['disclaimer'],
                    'professional_review_notice' => $definition['professional_review_notice'],
                    'reference_sources' => [
                        [
                            'title' => 'VitaScan — Questionário de Triagem — Ansiedade e Comportamento Alimentar',
                            'type' => 'editorial_source',
                            'validation_status' => 'not_clinically_validated',
                        ],
                        [
                            'title' => 'Sistema de Avaliação de Testes Psicológicos — SATEPSI',
                            'url' => 'https://satepsi.cfp.org.br/',
                            'type' => 'professional_guidance',
                        ],
                    ],
                ]);
            }

            foreach ($definition['questions'] as $questionDefinition) {
                $question = $questionnaire->questions()->updateOrCreate([
                    'code' => sprintf('Q%02d', $questionDefinition['id']),
                ], [
                    'position' => $questionDefinition['id'],
                    'text' => $questionDefinition['text'],
                    'response_type' => 'single_choice',
                    'requires_individual_attention' => $questionDefinition['requires_individual_attention'],
                ]);

                foreach ($definition['options'] as $position => $optionDefinition) {
                    $question->options()->updateOrCreate([
                        'value' => $optionDefinition['value'],
                    ], [
                        'label' => $optionDefinition['label'],
                        'position' => $position + 1,
                    ]);
                }
            }

            $rangeMessages = [
                'Poucos sinais relatados' => 'Foram relatados poucos sinais neste questionário. O resultado é apenas descritivo e deve ser considerado junto ao contexto individual.',
                'Alguns sinais de atenção' => 'Foram relatados alguns sinais que podem ser conversados com o profissional responsável.',
                'Sinais frequentes' => 'Foram relatados sinais frequentes. Recomenda-se conversar com profissional habilitado para contextualizar as respostas.',
                'Muitos sinais relatados' => 'Foram relatados muitos sinais. Procure o profissional responsável para acolhimento e avaliação adequada, sem interpretar esta faixa como diagnóstico.',
            ];

            foreach ($definition['descriptive_ranges'] as $position => $rangeDefinition) {
                $questionnaire->ranges()->updateOrCreate([
                    'position' => $position + 1,
                ], [
                    'minimum_score' => $rangeDefinition['minimum'],
                    'maximum_score' => $rangeDefinition['maximum'],
                    'description' => $rangeDefinition['label'],
                    'display_message' => $rangeMessages[$rangeDefinition['label']],
                ]);
            }
        });
    }
}
