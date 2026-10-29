<?php

namespace App\Domain\Screening;

use App\Models\User;

final class VitaAceaQuestionnaire
{
    public const CODE = 'VITA-ACEA';

    public const VERSION = 'VITA-ACEA-1.1';

    public const LEGACY_VERSION = 'VITA-ACEA-1.0';

    public const TITLE = 'Questionário descritivo de triagem sobre ansiedade e comportamento alimentar';

    public const EDITORIAL_STATUS = 'pending_professional_approval';

    public const RECALL_PERIOD_DAYS = 14;

    public const SCORING_RULES_VERSION = 'VITA-ACEA-SCORING-1.1';

    public const EXPECTED_QUESTION_COUNT = 10;

    public const EXPECTED_DESCRIPTIVE_QUESTION_COUNT = 1;

    public const VISUAL_QUESTION_CODE = 'VIS01';

    public const MINIMUM_OPTION_SCORE = 0;

    public const MAXIMUM_OPTION_SCORE = 3;

    public const MINIMUM_TOTAL_SCORE = 0;

    public const MAXIMUM_TOTAL_SCORE = 30;

    public const ATTENTION_QUESTION_CODES = ['Q02', 'Q05', 'Q06', 'Q09', 'Q10'];

    public const ATTENTION_NOTICE = 'Uma resposta marcada nestes itens merece conversa individual com profissional habilitado. Isoladamente, ela não diagnostica ansiedade, compulsão alimentar ou qualquer outra condição de saúde.';

    public const APPLICATION_ROLES = [
        User::ROLE_ADMIN,
        User::ROLE_PROFESSIONAL,
    ];

    public const INTERPRETATION_ROLES = [
        User::ROLE_ADMIN,
        User::ROLE_PROFESSIONAL,
    ];

    public const DISCLAIMER = 'Este questionário é uma triagem descritiva informal e não foi validado clinicamente. A pontuação não diagnostica ansiedade, compulsão alimentar ou qualquer outra condição de saúde.';

    public const PROFESSIONAL_REVIEW_NOTICE = 'O resultado deve ser contextualizado por profissional habilitado. Respostas isoladas e a pontuação total não substituem entrevista, avaliação clínica ou instrumento validado.';

    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'code' => self::CODE,
            'version' => self::VERSION,
            'title' => self::TITLE,
            'editorial_status' => self::EDITORIAL_STATUS,
            'recall_period_days' => self::RECALL_PERIOD_DAYS,
            'instructions' => 'Responda considerando os últimos 14 dias. Não existem respostas certas ou erradas. Selecione uma alternativa por pergunta.',
            'disclaimer' => self::DISCLAIMER,
            'professional_review_notice' => self::PROFESSIONAL_REVIEW_NOTICE,
            'application_roles' => self::APPLICATION_ROLES,
            'interpretation_roles' => self::INTERPRETATION_ROLES,
            'options' => self::options(),
            'questions' => self::questions(),
            'visual_question' => self::visualQuestion(),
            'descriptive_ranges' => self::descriptiveRanges(),
            'professional_approval' => [
                'required' => true,
                'approved_by' => null,
                'professional_registration' => null,
                'approved_at' => null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function scoringRulesSnapshot(?string $questionnaireVersion = null): array
    {
        $version = $questionnaireVersion ?? self::VERSION;

        return [
            'rules_version' => self::scoringRulesVersion($version),
            'calculation_method' => 'deterministic_sum',
            'question_count' => self::EXPECTED_QUESTION_COUNT,
            'descriptive_question_count' => $version === self::VERSION ? self::EXPECTED_DESCRIPTIVE_QUESTION_COUNT : 0,
            'descriptive_questions_affect_score' => false,
            'allowed_option_scores' => range(self::MINIMUM_OPTION_SCORE, self::MAXIMUM_OPTION_SCORE),
            'minimum_total_score' => self::MINIMUM_TOTAL_SCORE,
            'maximum_total_score' => self::MAXIMUM_TOTAL_SCORE,
            'attention_question_codes' => self::ATTENTION_QUESTION_CODES,
            'attention_trigger' => 'selected_option_score_greater_than_zero',
            'classification_source' => 'versioned_screening_ranges',
            'uses_artificial_intelligence' => false,
        ];
    }

    public static function scoringRulesVersion(string $questionnaireVersion): string
    {
        return $questionnaireVersion === self::LEGACY_VERSION
            ? 'VITA-ACEA-SCORING-1.0'
            : self::SCORING_RULES_VERSION;
    }

    /** @return list<array{value: int, label: string}> */
    public static function options(): array
    {
        return [
            ['value' => 0, 'label' => 'Nunca ou raramente'],
            ['value' => 1, 'label' => 'Às vezes'],
            ['value' => 2, 'label' => 'Frequentemente'],
            ['value' => 3, 'label' => 'Quase sempre'],
        ];
    }

    /** @return list<array{id: int, text: string, requires_individual_attention: bool}> */
    public static function questions(): array
    {
        return [
            [
                'id' => 1,
                'text' => 'Nos últimos 14 dias, com que frequência você sentiu ansiedade ou inquietação durante o dia?',
                'requires_individual_attention' => false,
            ],
            [
                'id' => 2,
                'text' => 'Quando sentiu ansiedade, teve vontade de comer mesmo sem perceber fome física?',
                'requires_individual_attention' => true,
            ],
            [
                'id' => 3,
                'text' => 'Teve dificuldade para controlar a vontade de comer doces ou outros alimentos?',
                'requires_individual_attention' => false,
            ],
            [
                'id' => 4,
                'text' => 'Comeu para tentar aliviar tristeza, estresse, solidão ou preocupação?',
                'requires_individual_attention' => false,
            ],
            [
                'id' => 5,
                'text' => 'Sentiu perda de controle e continuou comendo mesmo após perceber saciedade?',
                'requires_individual_attention' => true,
            ],
            [
                'id' => 6,
                'text' => 'Depois de comer, sentiu culpa ou arrependimento?',
                'requires_individual_attention' => true,
            ],
            [
                'id' => 7,
                'text' => 'Pensou em comida várias vezes ao dia, mesmo enquanto realizava outras atividades?',
                'requires_individual_attention' => false,
            ],
            [
                'id' => 8,
                'text' => 'Em momentos de preocupação, percebeu que comeu mais rápido ou em maior quantidade?',
                'requires_individual_attention' => false,
            ],
            [
                'id' => 9,
                'text' => 'Teve episódios repetidos em que sentiu que não conseguia parar de comer?',
                'requires_individual_attention' => true,
            ],
            [
                'id' => 10,
                'text' => 'A sua relação com a comida prejudicou sua autoestima ou sua rotina?',
                'requires_individual_attention' => true,
            ],
        ];
    }

    /** @return list<array{minimum: int, maximum: int, label: string}> */
    public static function descriptiveRanges(): array
    {
        return [
            ['minimum' => 0, 'maximum' => 7, 'label' => 'Poucos sinais relatados'],
            ['minimum' => 8, 'maximum' => 15, 'label' => 'Alguns sinais de atenção'],
            ['minimum' => 16, 'maximum' => 22, 'label' => 'Sinais frequentes'],
            ['minimum' => 23, 'maximum' => 30, 'label' => 'Muitos sinais relatados'],
        ];
    }

    /** @return array<string, mixed> */
    public static function visualQuestion(): array
    {
        return [
            'code' => self::VISUAL_QUESTION_CODE,
            'position' => 11,
            'text' => 'Qual destas formas chama mais a sua atenção neste momento?',
            'response_type' => 'descriptive_image_choice',
            'requires_individual_attention' => false,
            'instructions' => 'Escolha uma forma sem pensar por muito tempo. Esta escolha é apenas um convite à reflexão, não altera sua pontuação e não possui validade diagnóstica.',
            'options' => [
                ['value' => 1, 'visual_key' => 'grid_dense', 'title' => 'Sensação de prisão', 'description' => 'Esta escolha pode servir como convite para refletir sobre medo de errar, pressão por corresponder às expectativas e espaço reservado às próprias necessidades.'],
                ['value' => 2, 'visual_key' => 'rings_irregular', 'title' => 'Preocupação com o futuro', 'description' => 'Esta escolha pode servir como convite para observar antecipações sobre o amanhã e possíveis dificuldades para relaxar ou permanecer no presente.'],
                ['value' => 3, 'visual_key' => 'grid_irregular', 'title' => 'Sobrecarga mental', 'description' => 'Esta escolha pode servir como convite para perceber excesso de pensamentos, tentativas de prever problemas e busca constante por respostas.'],
                ['value' => 4, 'visual_key' => 'spirals_group', 'title' => 'Agitação constante', 'description' => 'Esta escolha pode servir como convite para refletir sobre ocupação contínua, culpa ao descansar e a sensação de nunca ter feito o suficiente.'],
                ['value' => 5, 'visual_key' => 'scribble', 'title' => 'Estado de alerta permanente', 'description' => 'Esta escolha pode servir como convite para observar quanto espaço existe na rotina para autocuidado e necessidades pessoais.'],
                ['value' => 6, 'visual_key' => 'squares', 'title' => 'Medo da mudança', 'description' => 'Esta escolha pode servir como convite para refletir sobre insegurança diante de mudanças, novos ciclos e recomeços.'],
                ['value' => 7, 'visual_key' => 'spiral_open', 'title' => 'Perfeccionismo e controle', 'description' => 'Esta escolha pode servir como convite para observar autocobrança e dificuldade para aceitar erros ou situações fora do próprio controle.'],
                ['value' => 8, 'visual_key' => 'zigzag', 'title' => 'Expectativa do pior', 'description' => 'Esta escolha pode servir como convite para perceber a tendência de permanecer em alerta para algo dar errado, mesmo quando tudo parece bem.'],
            ],
        ];
    }

    public static function isProfessionallyApproved(): bool
    {
        return self::EDITORIAL_STATUS === 'professionally_approved';
    }
}
