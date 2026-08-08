<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bioimpedance_client_id',
        'user_id',
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
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
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
}
