<?php

namespace App\Models\Screening\Concerns;

use App\Models\Screening\ScreeningQuestionnaire;
use App\Models\Screening\ScreeningQuestion;
use DomainException;

trait GuardsLockedQuestionnaireDefinition
{
    protected static function bootGuardsLockedQuestionnaireDefinition(): void
    {
        static::saving(function ($model): void {
            $model->guardQuestionnaireDefinition();
        });

        static::deleting(function ($model): void {
            $model->guardQuestionnaireDefinition();
        });
    }

    private function guardQuestionnaireDefinition(): void
    {
        $questionnaireId = $this->questionnaireDefinitionId();
        if (! $questionnaireId) {
            return;
        }

        $isLocked = ScreeningQuestionnaire::query()
            ->whereKey($questionnaireId)
            ->where(function ($query): void {
                $query->whereIn('status', [
                    ScreeningQuestionnaire::STATUS_ACTIVE,
                    ScreeningQuestionnaire::STATUS_RETIRED,
                ])->orWhereHas('sessions');
            })
            ->exists();

        if ($isLocked) {
            throw new DomainException('A definição de uma versão ativa, descontinuada ou já aplicada é imutável. Crie uma nova versão do questionário.');
        }
    }

    private function questionnaireDefinitionId(): ?int
    {
        if (isset($this->screening_questionnaire_id)) {
            return (int) $this->screening_questionnaire_id;
        }

        if (isset($this->screening_question_id)) {
            return ScreeningQuestion::query()
                ->whereKey($this->screening_question_id)
                ->value('screening_questionnaire_id');
        }

        return null;
    }
}
