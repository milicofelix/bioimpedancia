<?php

namespace App\Domain\Screening;

final class CurrentAnxietyQuestionnaire
{
    public const CODE = 'VITA-TAA';
    public const VERSION = 'VITA-TAA-1.0';
    public const TITLE = 'Transtorno Atual da Ansiedade';
    public const RULES_VERSION = 'VITA-TAA-DESCRIPTIVE-1.0';
    public const DISCLAIMER = 'Este instrumento visual é uma reflexão descritiva informal e não foi validado clinicamente. A escolha não diagnostica ansiedade ou qualquer outra condição de saúde.';

    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'code' => self::CODE,
            'version' => self::VERSION,
            'title' => self::TITLE,
            'description' => 'Instrumento visual descritivo com escolha de uma entre oito formas e devolutiva imediata sem pontuação.',
            'recall_period_days' => null,
            'consent_text' => 'Declaro que compreendi a finalidade exclusivamente descritiva desta escolha visual, autorizo o registro da resposta e sei que o resultado não constitui diagnóstico.',
            'disclaimer' => self::DISCLAIMER,
            'professional_review_notice' => 'A escolha visual deve ser tratada apenas como convite à reflexão e contextualizada por profissional habilitado.',
            'visual_question' => VitaAceaQuestionnaire::visualQuestion(),
            'descriptive_ranges' => [[
                'minimum' => 0,
                'maximum' => 0,
                'label' => 'Reflexão visual descritiva',
                'message' => 'A forma escolhida oferece um convite à reflexão e não representa classificação clínica ou diagnóstico.',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public static function rulesSnapshot(): array
    {
        return [
            'rules_version' => self::RULES_VERSION,
            'calculation_method' => 'descriptive_visual_choice',
            'question_count' => 1,
            'descriptive_question_count' => 1,
            'descriptive_questions_affect_score' => false,
            'minimum_total_score' => 0,
            'maximum_total_score' => 0,
            'classification_source' => 'selected_visual_option',
            'uses_artificial_intelligence' => false,
        ];
    }
}
