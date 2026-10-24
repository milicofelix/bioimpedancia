<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceKnowledgeChunk;
use App\Models\Bioimpedance\BioimpedanceKnowledgeDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KnowledgeDocumentWorkflow
{
    public function createDraft(array $data, User $actor): BioimpedanceKnowledgeDocument
    {
        return DB::transaction(function () use ($data, $actor) {
            $existing = BioimpedanceKnowledgeDocument::query()
                ->where('title', $data['title'])
                ->where('version', $data['version'])
                ->exists();
            if ($existing) {
                throw ValidationException::withMessages(['version' => 'Esta versão já está cadastrada para o documento.']);
            }

            $chunks = $data['chunks'];
            unset($data['chunks']);
            $document = BioimpedanceKnowledgeDocument::query()->create([
                ...$data,
                'status' => BioimpedanceKnowledgeDocument::STATUS_DRAFT,
                'created_by_user_id' => $actor->id,
                'approved' => false,
            ]);

            foreach ($chunks as $index => $chunk) {
                $content = trim($chunk['content']);
                BioimpedanceKnowledgeChunk::query()->create([
                    'bioimpedance_knowledge_document_id' => $document->id,
                    'chunk_key' => $this->chunkKey($document, $chunk['section'], $index, $content),
                    'section' => $chunk['section'],
                    'page' => $chunk['page'] ?? null,
                    'language' => $chunk['language'] ?? 'pt-BR',
                    'tags' => $chunk['tags'] ?? [],
                    'content' => $content,
                    'checksum' => hash('sha256', $content),
                ]);
            }

            return $document->load(['chunks', 'createdBy']);
        });
    }

    public function transition(BioimpedanceKnowledgeDocument $document, string $action, User $actor, ?int $replacementId = null): BioimpedanceKnowledgeDocument
    {
        return DB::transaction(function () use ($document, $action, $actor, $replacementId) {
            $document = BioimpedanceKnowledgeDocument::query()->lockForUpdate()->findOrFail($document->id);

            match ($action) {
                'review' => $this->review($document, $actor),
                'approve' => $this->approve($document, $actor),
                'publish' => $this->publish($document, $actor),
                'supersede' => $this->supersede($document, $replacementId),
            };

            return $document->refresh()->load(['chunks', 'createdBy', 'reviewedBy', 'approvedBy', 'publishedBy', 'supersededBy']);
        });
    }

    private function review(BioimpedanceKnowledgeDocument $document, User $actor): void
    {
        $this->requireStatus($document, BioimpedanceKnowledgeDocument::STATUS_DRAFT);
        $document->update(['status' => BioimpedanceKnowledgeDocument::STATUS_REVIEWED, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()]);
    }

    private function approve(BioimpedanceKnowledgeDocument $document, User $actor): void
    {
        $this->requireStatus($document, BioimpedanceKnowledgeDocument::STATUS_REVIEWED);
        $document->update(['status' => BioimpedanceKnowledgeDocument::STATUS_APPROVED, 'approved' => true, 'approved_by_user_id' => $actor->id, 'approved_at' => now()]);
    }

    private function publish(BioimpedanceKnowledgeDocument $document, User $actor): void
    {
        $this->requireStatus($document, BioimpedanceKnowledgeDocument::STATUS_APPROVED);
        $document->update(['status' => BioimpedanceKnowledgeDocument::STATUS_PUBLISHED, 'published_by_user_id' => $actor->id, 'published_at' => now()]);
    }

    private function supersede(BioimpedanceKnowledgeDocument $document, ?int $replacementId): void
    {
        $this->requireStatus($document, BioimpedanceKnowledgeDocument::STATUS_PUBLISHED);
        $replacement = BioimpedanceKnowledgeDocument::query()->findOrFail($replacementId);
        if ($replacement->id === $document->id || ! $replacement->isPublished() || $replacement->title !== $document->title) {
            throw ValidationException::withMessages(['replacement_document_id' => 'Selecione uma versão publicada do mesmo documento.']);
        }
        $document->update(['status' => BioimpedanceKnowledgeDocument::STATUS_SUPERSEDED, 'superseded_by_document_id' => $replacement->id, 'superseded_at' => now()]);
    }

    private function requireStatus(BioimpedanceKnowledgeDocument $document, string $expected): void
    {
        if ($document->status !== $expected) {
            throw ValidationException::withMessages(['action' => "A transição exige que o documento esteja no estado {$expected}."]);
        }
    }

    private function chunkKey(BioimpedanceKnowledgeDocument $document, string $section, int $index, string $content): string
    {
        return Str::limit(Str::slug($document->title.'-'.$document->version.'-'.$section), 160, '')
            .'-'.($index + 1).'-'.substr(hash('sha256', $content), 0, 10);
    }
}
