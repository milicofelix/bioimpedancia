<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceAiAnalysisOutput extends Model
{
    protected $fillable = [
        'bioimpedance_ai_analysis_request_id',
        'raw_response',
        'structured_claims',
        'blocked_claims',
        'professional_observation',
        'care_guidance',
        'validation_status',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'structured_claims' => 'array',
            'blocked_claims' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAiAnalysisRequest::class, 'bioimpedance_ai_analysis_request_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
