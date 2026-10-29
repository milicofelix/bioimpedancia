<?php

namespace App\Models\Screening;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningSessionReview extends Model
{
    protected $fillable = [
        'screening_session_id',
        'professional_notes',
        'referral_notes',
        'updated_by_user_id',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScreeningSession::class, 'screening_session_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
