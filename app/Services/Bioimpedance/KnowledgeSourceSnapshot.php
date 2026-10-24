<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceKnowledgeChunk;

class KnowledgeSourceSnapshot
{
    public function fromChunk(BioimpedanceKnowledgeChunk $chunk): array
    {
        $chunk->loadMissing('document');

        return [
            'chunk_key' => $chunk->chunk_key,
            'chunk_checksum' => $chunk->checksum,
            'section' => $chunk->section,
            'page' => $chunk->page,
            'language' => $chunk->language,
            'tags' => $chunk->tags ?? [],
            'content' => $chunk->content,
            'document_id' => $chunk->document?->id,
            'document_title' => $chunk->document?->title,
            'document_type' => $chunk->document?->document_type,
            'document_version' => $chunk->document?->version,
            'source_url' => $chunk->document?->source_url,
            'publication_status' => $chunk->document?->status,
            'published_at' => $chunk->document?->published_at?->toIso8601String(),
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
