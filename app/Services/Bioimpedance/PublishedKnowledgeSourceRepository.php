<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceKnowledgeDocument;
use Illuminate\Support\Collection;

class PublishedKnowledgeSourceRepository
{
    public function matching(array $tags, array $chunkKeys = []): Collection
    {
        $documents = BioimpedanceKnowledgeDocument::query()
            ->with('chunks.document')
            ->where('status', BioimpedanceKnowledgeDocument::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->unique('title');

        return $documents
            ->flatMap->chunks
            ->filter(function ($chunk) use ($tags, $chunkKeys) {
                if ($chunk->language !== 'pt-BR') {
                    return false;
                }

                return in_array($chunk->chunk_key, $chunkKeys, true)
                    || count(array_intersect($chunk->tags ?? [], $tags)) > 0;
            })
            ->values();
    }
}
