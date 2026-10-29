<?php

namespace App\Models\Screening;

use App\Models\User;
use DomainException;
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

    protected static function booted(): void
    {
        static::updating(function (self $questionnaire): void {
            $originalStatus = $questionnaire->getOriginal('status');
            $locked = in_array($originalStatus, [self::STATUS_ACTIVE, self::STATUS_RETIRED], true)
                || $questionnaire->sessions()->exists();
            $dirtyFields = array_keys($questionnaire->getDirty());
            $onlyRetiring = $questionnaire->status === self::STATUS_RETIRED
                && array_diff($dirtyFields, ['status', 'updated_at']) === [];

            if ($locked && ! $onlyRetiring) {
                throw new DomainException('Uma versão ativa, descontinuada ou já aplicada é imutável. Crie uma nova versão para alterar sua definição.');
            }
        });

        static::deleting(function (self $questionnaire): void {
            if ($questionnaire->status !== self::STATUS_DRAFT || $questionnaire->sessions()->exists()) {
                throw new DomainException('Somente versões em rascunho e nunca aplicadas podem ser excluídas.');
            }
        });
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
