<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceReportShare extends Model
{
    protected $fillable = [
        'bioimpedance_assessment_id',
        'created_by_user_id',
        'token_hash',
        'channel',
        'recipient',
        'message',
        'expires_at',
        'revoked_at',
        'viewed_at',
        'view_count',
        'last_viewed_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAssessment::class, 'bioimpedance_assessment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
