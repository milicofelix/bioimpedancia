<?php

namespace App\Models\Bioimpedance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BioimpedanceKnowledgeChunk extends Model
{
    protected $fillable = [
        'bioimpedance_knowledge_document_id',
        'chunk_key',
        'section',
        'page',
        'language',
        'tags',
        'content',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceKnowledgeDocument::class, 'bioimpedance_knowledge_document_id');
    }

    public function analysisSources(): HasMany
    {
        return $this->hasMany(BioimpedanceAiAnalysisSource::class);
    }
}
