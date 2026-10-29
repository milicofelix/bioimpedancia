<?php

namespace App\Models\Screening;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScreeningOption extends Model
{
    protected $fillable = [
        'screening_question_id',
        'label',
        'value',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'position' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestion::class, 'screening_question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ScreeningAnswer::class);
    }
}
