<?php

namespace App\Models\Bioimpedance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceAiAnalysisSource extends Model
{
    protected $fillable = [
        'bioimpedance_ai_analysis_request_id',
        'bioimpedance_knowledge_chunk_id',
        'relevance_score',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAiAnalysisRequest::class, 'bioimpedance_ai_analysis_request_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceKnowledgeChunk::class, 'bioimpedance_knowledge_chunk_id');
    }
}
