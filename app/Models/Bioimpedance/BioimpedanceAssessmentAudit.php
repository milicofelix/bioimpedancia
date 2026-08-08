<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceAssessmentAudit extends Model
{
    protected $fillable = [
        'bioimpedance_assessment_id',
        'user_id',
        'action',
        'reason',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAssessment::class, 'bioimpedance_assessment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
