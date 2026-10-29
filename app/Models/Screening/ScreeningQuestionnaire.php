<?php

namespace App\Models\Screening;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningQuestionnaire extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PROFESSIONAL_APPROVAL = 'pending_professional_approval';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'code',
        'name',
        'description',
        'version',
        'status',
        'recall_period_days',
        'consent_text',
        'disclaimer',
        'professional_review_notice',
        'reference_sources',
        'approved_by_user_id',
        'approved_by_name',
        'approved_profession',
        'professional_registration',
        'approval_attestations',
        'approval_notes',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'recall_period_days' => 'integer',
            'reference_sources' => 'array',
            'approval_attestations' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ScreeningQuestion::class)->orderBy('position');
    }

    public function ranges(): HasMany
    {
        return $this->hasMany(ScreeningRange::class)->orderBy('position');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ScreeningSession::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isAvailableForApplication(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->approved_at !== null
            && filled($this->professional_registration);
    }
}
