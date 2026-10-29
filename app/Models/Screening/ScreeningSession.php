<?php

namespace App\Models\Screening;

use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_STARTED = 'started';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'bioimpedance_client_id',
        'screening_questionnaire_id',
        'requested_by_user_id',
        'supersedes_session_id',
        'cancelled_by_user_id',
        'status',
        'questionnaire_code',
        'questionnaire_version',
        'questionnaire_name',
        'requested_by_name',
        'consent_text_snapshot',
        'disclaimer_snapshot',
        'consent_accepted_at',
        'started_at',
        'finalized_at',
        'cancelled_at',
        'cancellation_reason',
        'correction_reason',
        'total_score',
        'screening_range_id',
        'result_description',
        'result_message',
    ];

    protected function casts(): array
    {
        return [
            'consent_accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_score' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $session): void {
            if ($session->originalStatusIsTerminal()) {
                throw new DomainException('Uma triagem finalizada ou cancelada não pode ser alterada. Crie uma nova aplicação para registrar correções.');
            }
        });

        static::deleting(function (self $session): void {
            if ($session->statusIsTerminal()) {
                throw new DomainException('Uma triagem finalizada ou cancelada não pode ser excluída.');
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceClient::class, 'bioimpedance_client_id');
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestionnaire::class, 'screening_questionnaire_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function resultRange(): BelongsTo
    {
        return $this->belongsTo(ScreeningRange::class, 'screening_range_id');
    }

    public function supersededSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_session_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_session_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ScreeningAnswer::class)->orderBy('id');
    }

    public function statusIsTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_FINALIZED, self::STATUS_CANCELLED], true);
    }

    private function originalStatusIsTerminal(): bool
    {
        return in_array($this->getOriginal('status'), [self::STATUS_FINALIZED, self::STATUS_CANCELLED], true);
    }
}
