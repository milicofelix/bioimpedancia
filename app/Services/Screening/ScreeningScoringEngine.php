<?php

namespace App\Services\Screening;

use App\Domain\Screening\ScreeningScore;
use App\Models\Screening\ScreeningQuestion;
use App\Models\Screening\ScreeningQuestionnaire;
use DomainException;

class ScreeningScoringEngine
{
    /**
     * @param  array<int, int>  $selectedOptionIdsByQuestionId
     */
    public function score(ScreeningQuestionnaire $questionnaire, array $selectedOptionIdsByQuestionId): ScreeningScore
    {
        $questionnaire->loadMissing(['questions.options', 'ranges']);
        $this->assertDefinitionIsDeterministic($questionnaire);

        $questions = $questionnaire->questions;
        $knownQuestionIds = $questions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $answeredQuestionIds = array_map('intval', array_keys($selectedOptionIdsByQuestionId));
        $missingQuestionIds = array_values(array_diff($knownQuestionIds, $answeredQuestionIds));
        $unknownQuestionIds = array_values(array_diff($answeredQuestionIds, $knownQuestionIds));

        if ($missingQuestionIds !== []) {
            throw new DomainException('Todas as perguntas devem ser respondidas antes da pontuação.');
        }

        if ($unknownQuestionIds !== []) {
            throw new DomainException('A resposta contém perguntas que não pertencem a esta versão do questionário.');
        }

        $answers = [];
        $attentionQuestionCodes = [];
        $total = 0;

        foreach ($questions as $question) {
            $selectedOptionId = (int) $selectedOptionIdsByQuestionId[$question->id];
            $option = $question->options->firstWhere('id', $selectedOptionId);

            if (! $option) {
                throw new DomainException("A alternativa selecionada para {$question->code} não pertence a esta pergunta.");
            }

            $score = (int) $option->value;
            $requiresAttention = $question->requires_individual_attention && $score > 0;
            $total += $score;

            if ($requiresAttention) {
                $attentionQuestionCodes[] = $question->code;
            }

            $answers[] = [
                'question_id' => $question->id,
                'question_code' => $question->code,
                'question_text' => $question->text,
                'option_id' => $option->id,
                'response_label' => $option->label,
                'response_value' => $option->value,
                'score' => $score,
                'requires_individual_attention' => $requiresAttention,
            ];
        }

        $matchingRanges = $questionnaire->ranges
            ->filter(fn ($range) => $total >= $range->minimum_score && $total <= $range->maximum_score)
            ->values();

        if ($matchingRanges->count() !== 1) {
            throw new DomainException('A pontuação não corresponde exatamente a uma faixa descritiva válida.');
        }

        $range = $matchingRanges->first();

        return new ScreeningScore(
            total: $total,
            rangeId: $range->id,
            rangeDescription: $range->description,
            rangeMessage: $range->display_message,
            answers: $answers,
            attentionQuestionCodes: $attentionQuestionCodes,
        );
    }

    public function assertDefinitionIsDeterministic(ScreeningQuestionnaire $questionnaire): void
    {
        $questionnaire->loadMissing(['questions.options', 'ranges']);
        $questions = $questionnaire->questions;
        $ranges = $questionnaire->ranges->sortBy('minimum_score')->values();

        if ($questions->isEmpty()) {
            throw new DomainException('O questionário precisa possuir pelo menos uma pergunta.');
        }

        if ($ranges->isEmpty()) {
            throw new DomainException('O questionário precisa possuir faixas de resultado.');
        }

        $minimumTotal = 0;
        $maximumTotal = 0;

        foreach ($questions as $question) {
            if ($question->response_type !== ScreeningQuestion::RESPONSE_SINGLE_CHOICE) {
                throw new DomainException("O tipo de resposta de {$question->code} ainda não possui regra determinística.");
            }

            if ($question->options->isEmpty()) {
                throw new DomainException("A pergunta {$question->code} não possui alternativas.");
            }

            if ($question->options->pluck('value')->duplicates()->isNotEmpty()) {
                throw new DomainException("A pergunta {$question->code} possui alternativas com pontuações duplicadas.");
            }

            $minimumTotal += (int) $question->options->min('value');
            $maximumTotal += (int) $question->options->max('value');
        }

        $expectedMinimum = $minimumTotal;
        foreach ($ranges as $range) {
            if ($range->minimum_score > $range->maximum_score) {
                throw new DomainException("A faixa {$range->description} possui limites invertidos.");
            }

            if ($range->minimum_score !== $expectedMinimum) {
                throw new DomainException('As faixas de resultado possuem lacuna, sobreposição ou não iniciam na pontuação mínima possível.');
            }

            $expectedMinimum = $range->maximum_score + 1;
        }

        if ($expectedMinimum - 1 !== $maximumTotal) {
            throw new DomainException('As faixas de resultado não terminam na pontuação máxima possível.');
        }
    }
}
