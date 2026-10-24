<?php

namespace App\Models\Bioimpedance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BioimpedanceKnowledgeDocument extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'title',
        'document_type',
        'manufacturer',
        'device_model',
        'version',
        'source_url',
        'status',
        'created_by_user_id',
        'approved',
        'approved_by_user_id',
        'approved_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'published_by_user_id',
        'published_at',
        'superseded_by_document_id',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_document_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
