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
        'email',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'height_cm' => 'decimal:2',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(BioimpedanceAssessment::class);
    }
}
