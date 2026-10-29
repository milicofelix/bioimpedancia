<?php

namespace App\Models\Screening;

use App\Models\Screening\Concerns\GuardsLockedQuestionnaireDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningQuestion extends Model
{
    use GuardsLockedQuestionnaireDefinition;

    public const RESPONSE_SINGLE_CHOICE = 'single_choice';

    public const RESPONSE_DESCRIPTIVE_IMAGE_CHOICE = 'descriptive_image_choice';

    protected $fillable = [
        'screening_questionnaire_id',
        'code',
        'position',
        'text',
        'response_type',
        'requires_individual_attention',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'requires_individual_attention' => 'boolean',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestionnaire::class, 'screening_questionnaire_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ScreeningOption::class)->orderBy('position');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ScreeningAnswer::class);
    }
}
