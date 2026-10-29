<?php

namespace App\Models\Screening;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningRange extends Model
{
    protected $fillable = [
        'screening_questionnaire_id',
        'position',
        'minimum_score',
        'maximum_score',
        'description',
        'display_message',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'minimum_score' => 'integer',
            'maximum_score' => 'integer',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestionnaire::class, 'screening_questionnaire_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ScreeningSession::class);
    }
}
