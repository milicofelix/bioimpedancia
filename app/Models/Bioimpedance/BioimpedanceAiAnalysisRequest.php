<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BioimpedanceAiAnalysisRequest extends Model
{
    protected $fillable = [
        'bioimpedance_assessment_id',
        'requested_by_user_id',
        'prompt_version',
        'reference_version',
        'model_name',
        'status',
        'structured_context',
        'validation_errors',
    ];

    protected function casts(): array
    {
        return [
            'structured_context' => 'array',
            'validation_errors' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAssessment::class, 'bioimpedance_assessment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(BioimpedanceAiAnalysisSource::class);
    }

    public function output(): HasOne
    {
        return $this->hasOne(BioimpedanceAiAnalysisOutput::class);
    }
}
