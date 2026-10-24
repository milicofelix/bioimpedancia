<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bioimpedance\StoreKnowledgeDocumentRequest;
use App\Http\Requests\Bioimpedance\TransitionKnowledgeDocumentRequest;
use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceKnowledgeDocument;
use App\Services\Bioimpedance\KnowledgeDocumentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KnowledgeDocumentController extends Controller
{
    public function __construct(private readonly KnowledgeDocumentWorkflow $workflow) {}

    public function index(): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        return response()->json(['documents' => $this->documents()]);
    }

    public function store(StoreKnowledgeDocumentRequest $request): JsonResponse
    {
        $document = DB::transaction(function () use ($request) {
            $document = $this->workflow->createDraft($request->validated(), $request->user());
            $this->audit($request, $document, 'knowledge_document.created', 'Referência criada como rascunho.');

            return $document;
        });

        return response()->json(['document' => $this->payload($document), 'documents' => $this->documents()], 201);
    }

    public function transition(TransitionKnowledgeDocumentRequest $request, BioimpedanceKnowledgeDocument $document): JsonResponse
    {
        $validated = $request->validated();
        $document = DB::transaction(function () use ($request, $document, $validated) {
            $oldStatus = $document->status;
            $document = $this->workflow->transition($document, $validated['action'], $request->user(), $validated['replacement_document_id'] ?? null);
            $this->audit($request, $document, 'knowledge_document.'.$validated['action'], 'Estado editorial da referência alterado.', $oldStatus);

            return $document;
        });

        return response()->json(['document' => $this->payload($document), 'documents' => $this->documents()]);
    }

    private function documents(): array
    {
        return BioimpedanceKnowledgeDocument::query()
            ->with(['chunks', 'createdBy', 'reviewedBy', 'approvedBy', 'publishedBy', 'supersededBy'])
            ->latest()
            ->get()
            ->map(fn (BioimpedanceKnowledgeDocument $document) => $this->payload($document))
            ->all();
    }

    private function payload(BioimpedanceKnowledgeDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'document_type' => $document->document_type,
            'manufacturer' => $document->manufacturer,
            'device_model' => $document->device_model,
            'version' => $document->version,
            'source_url' => $document->source_url,
            'status' => $document->status,
            'created_by' => $document->createdBy?->name,
            'reviewed_by' => $document->reviewedBy?->name,
            'reviewed_at' => $document->reviewed_at?->toIso8601String(),
            'approved_by' => $document->approvedBy?->name,
            'approved_at' => $document->approved_at?->toIso8601String(),
            'published_by' => $document->publishedBy?->name,
            'published_at' => $document->published_at?->toIso8601String(),
            'superseded_by' => $document->supersededBy ? ['id' => $document->supersededBy->id, 'version' => $document->supersededBy->version] : null,
            'superseded_at' => $document->superseded_at?->toIso8601String(),
            'chunks' => $document->chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'section' => $chunk->section,
                'page' => $chunk->page,
                'language' => $chunk->language,
                'tags' => $chunk->tags ?? [],
                'content' => $chunk->content,
                'checksum' => $chunk->checksum,
            ])->all(),
        ];
    }

    private function audit(Request $request, BioimpedanceKnowledgeDocument $document, string $action, string $description, ?string $oldStatus = null): void
    {
        AppAudit::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $document::class,
            'auditable_id' => $document->id,
            'description' => $description,
            'old_values' => $oldStatus ? ['status' => $oldStatus] : null,
            'new_values' => ['status' => $document->status, 'title' => $document->title, 'version' => $document->version],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
