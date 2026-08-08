<?php

namespace App\Models\Bioimpedance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BioimpedanceClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'birth_date',
        'biological_sex',
        'height_cm',
        'phone',
        'phone_digits',
        'email',
        'cpf',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'consent_accepted_at',
        'next_assessment_at',
        'inactivated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'height_cm' => 'decimal:2',
            'consent_accepted_at' => 'datetime',
            'next_assessment_at' => 'date',
            'inactivated_at' => 'datetime',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(BioimpedanceAssessment::class);
    }
}
