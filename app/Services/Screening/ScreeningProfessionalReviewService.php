<?php

namespace App\Services\Screening;

use App\Models\AppAudit;
use App\Models\Screening\ScreeningSession;
use App\Models\Screening\ScreeningSessionReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreeningProfessionalReviewService
{
    public function details(Request $request, ScreeningSession $session): array
    {
        $this->authorize($request, $session);
        $session->loadMissing(['answers', 'client', 'professionalReview.updatedBy']);

        $audits = AppAudit::query()
            ->with('user:id,name')
            ->where('auditable_type', ScreeningSession::class)
            ->where('auditable_id', $session->id)
            ->oldest()
            ->get();

        return $this->payload($session, $audits);
    }

    public function update(Request $request, ScreeningSession $session, array $data): array
    {
        $user = $this->authorize($request, $session);

        if ($session->status !== ScreeningSession::STATUS_FINALIZED) {
            throw ValidationException::withMessages([
                'session' => 'Observações e encaminhamentos só podem ser registrados após a finalização.',
            ]);
        }

        DB::transaction(function () use ($request, $session, $data, $user): void {
            $existing = ScreeningSessionReview::query()
                ->where('screening_session_id', $session->id)
                ->lockForUpdate()
                ->first();
            $oldValues = $existing?->only(['professional_notes', 'referral_notes']) ?? [];

            ScreeningSessionReview::query()->updateOrCreate(
                ['screening_session_id' => $session->id],
                [
                    'professional_notes' => $data['professional_notes'] ?? null,
                    'referral_notes' => $data['referral_notes'] ?? null,
                    'updated_by_user_id' => $user->id,
                ],
            );

            AppAudit::query()->create([
                'user_id' => $user->id,
                'action' => 'screening.professional_review.updated',
                'auditable_type' => ScreeningSession::class,
                'auditable_id' => $session->id,
                'description' => 'Observação profissional e encaminhamento da triagem atualizados',
                'old_values' => $oldValues,
                'new_values' => [
                    'professional_notes' => $data['professional_notes'] ?? null,
                    'referral_notes' => $data['referral_notes'] ?? null,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
        });

        return $this->details($request, $session->refresh());
    }

    private function authorize(Request $request, ScreeningSession $session): User
    {
        $user = $request->user();
        abort_unless(
            $user?->isClinicalProfessional()
                && $user->isActive()
                && ($user->isAdmin() || $session->requested_by_user_id === $user->id),
            403,
        );

        return $user;
    }

    private function payload(ScreeningSession $session, Collection $audits): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'client_name' => $session->client->full_name,
            'questionnaire_name' => $session->questionnaire_name,
            'questionnaire_version' => $session->questionnaire_version,
            'requested_by_name' => $session->requested_by_name,
            'created_at' => $session->created_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'finalized_at' => $session->finalized_at?->toIso8601String(),
            'consent' => [
                'text' => $session->consent_text_snapshot,
                'accepted_at' => $session->consent_accepted_at?->toIso8601String(),
            ],
            'result' => [
                'total_score' => $session->total_score,
                'description' => $session->result_description,
                'message' => $session->result_message,
                'document_authenticity_code' => $session->document_authenticity_code,
            ],
            'answers' => $session->answers->map(fn ($answer) => [
                'question_code' => $answer->question_code,
                'question_text' => $answer->question_text_snapshot,
                'response_label' => $answer->response_label_snapshot,
                'score' => $answer->score,
                'requires_individual_attention' => $answer->requires_individual_attention,
            ])->values(),
            'professional_review' => [
                'professional_notes' => $session->professionalReview?->professional_notes,
                'referral_notes' => $session->professionalReview?->referral_notes,
                'updated_at' => $session->professionalReview?->updated_at?->toIso8601String(),
                'updated_by_name' => $session->professionalReview?->updatedBy?->name,
            ],
            'audits' => $audits->map(fn (AppAudit $audit) => [
                'action' => $audit->action,
                'description' => $audit->description,
                'user_name' => $audit->user?->name ?? 'Acesso público',
                'created_at' => $audit->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
