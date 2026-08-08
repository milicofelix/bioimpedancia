<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BioimpedanceKnowledgeDocument extends Model
{
    protected $fillable = [
        'title',
        'document_type',
        'manufacturer',
        'device_model',
        'version',
        'source_url',
        'approved',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(BioimpedanceKnowledgeChunk::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
