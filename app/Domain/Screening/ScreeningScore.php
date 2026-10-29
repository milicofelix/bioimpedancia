<?php

namespace App\Domain\Screening;

final readonly class ScreeningScore
{
    /**
     * @param  list<array{
     *     question_id: int,
     *     question_code: string,
     *     question_text: string,
     *     option_id: int,
     *     response_label: string,
     *     response_value: int,
     *     score: int,
     *     requires_individual_attention: bool
     * }>  $answers
     * @param  list<string>  $attentionQuestionCodes
     */
    public function __construct(
        public int $total,
        public int $rangeId,
        public string $rangeDescription,
        public string $rangeMessage,
        public array $answers,
        public array $attentionQuestionCodes,
    ) {}
}
