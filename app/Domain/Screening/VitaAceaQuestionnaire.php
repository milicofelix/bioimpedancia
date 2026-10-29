<?php

namespace App\Domain\Screening;

use App\Models\User;

final class VitaAceaQuestionnaire
{
    public const CODE = 'VITA-ACEA';

    public const VERSION = 'VITA-ACEA-1.0';

    public const TITLE = 'Questionário descritivo de triagem sobre ansiedade e comportamento alimentar';

    public const EDITORIAL_STATUS = 'pending_professional_approval';

    public const RECALL_PERIOD_DAYS = 14;

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
            'descriptive_ranges' => self::descriptiveRanges(),
            'professional_approval' => [
                'required' => true,
                'approved_by' => null,
                'professional_registration' => null,
                'approved_at' => null,
            ],
        ];
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

    public static function isProfessionallyApproved(): bool
    {
        return self::EDITORIAL_STATUS === 'professionally_approved';
    }
}
