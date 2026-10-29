<?php

namespace App\Models\Screening;

use App\Models\Screening\Concerns\GuardsLockedQuestionnaireDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningOption extends Model
{
    use GuardsLockedQuestionnaireDefinition;

    protected $fillable = [
        'screening_question_id',
        'label',
        'value',
        'position',
        'display_metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'position' => 'integer',
            'display_metadata' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestion::class, 'screening_question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ScreeningAnswer::class);
    }
}
