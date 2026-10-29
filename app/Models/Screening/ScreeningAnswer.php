<?php

namespace App\Models\Screening;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningAnswer extends Model
{
    protected $fillable = [
        'screening_session_id',
        'screening_question_id',
        'screening_option_id',
        'question_code',
        'questionnaire_version',
        'question_text_snapshot',
        'response_label_snapshot',
        'response_value',
        'score',
        'requires_individual_attention',
    ];

    protected function casts(): array
    {
        return [
            'response_value' => 'integer',
            'score' => 'integer',
            'requires_individual_attention' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $answer): void {
            $answer->guardFinalizedSession();
        });

        static::deleting(function (self $answer): void {
            $answer->guardFinalizedSession();
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScreeningSession::class, 'screening_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestion::class, 'screening_question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(ScreeningOption::class, 'screening_option_id');
    }

    private function guardFinalizedSession(): void
    {
        $isTerminal = ScreeningSession::query()
            ->whereKey($this->screening_session_id)
            ->whereIn('status', [ScreeningSession::STATUS_FINALIZED, ScreeningSession::STATUS_CANCELLED])
            ->exists();

        if ($isTerminal) {
            throw new DomainException('Respostas de uma triagem finalizada ou cancelada são imutáveis. Crie uma nova aplicação para registrar correções.');
        }
    }
}
