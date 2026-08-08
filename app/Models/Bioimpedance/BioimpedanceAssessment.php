<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BioimpedanceAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bioimpedance_client_id',
        'user_id',
        'corrected_by_user_id',
        'correction_count',
        'age_at_assessment',
        'height_cm_at_assessment',
        'biological_sex_at_assessment',
        'device_model',
        'reference_version',
        'evaluated_at',
        'weight_kg',
        'scale_bmi',
        'calculated_bmi',
        'bmi_difference',
        'body_fat_percentage',
        'skeletal_muscle_percentage',
        'resting_metabolism_kcal',
        'body_age',
        'visceral_fat_level',
        'analysis',
        'notes',
        'canceled_at',
        'canceled_by_user_id',
        'cancellation_reason',
        'report_issued_at',
        'report_issue_count',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
            'canceled_at' => 'datetime',
            'report_issued_at' => 'datetime',
            'height_cm_at_assessment' => 'decimal:1',
            'weight_kg' => 'decimal:2',
            'scale_bmi' => 'decimal:1',
            'calculated_bmi' => 'decimal:1',
            'bmi_difference' => 'decimal:2',
            'body_fat_percentage' => 'decimal:1',
            'skeletal_muscle_percentage' => 'decimal:1',
            'visceral_fat_level' => 'decimal:1',
            'analysis' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceClient::class, 'bioimpedance_client_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by_user_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(BioimpedanceAssessmentAudit::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(BioimpedanceReportShare::class);
    }
}
