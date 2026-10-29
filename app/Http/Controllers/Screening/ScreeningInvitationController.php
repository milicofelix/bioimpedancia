<?php

namespace App\Http\Controllers\Screening;

use App\Http\Controllers\Controller;
use App\Http\Requests\Screening\ResendScreeningInvitationRequest;
use App\Http\Requests\Screening\StoreScreeningInvitationRequest;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Screening\ScreeningQuestionnaire;
use App\Models\Screening\ScreeningSession;
use App\Services\Screening\ScreeningInvitationService;
use App\Services\Screening\ScreeningHistoryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningInvitationController extends Controller
{
    public function __construct(
        private readonly ScreeningInvitationService $invitations,
        private readonly ScreeningHistoryPresenter $history,
    ) {}

    public function index(Request $request, BioimpedanceClient $client): JsonResponse
    {
        abort_unless($request->user()?->isClinicalProfessional() && $request->user()->isActive(), 403);

        $questionnaires = ScreeningQuestionnaire::query()
            ->withCount(['questions', 'ranges'])
            ->orderBy('name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (ScreeningQuestionnaire $questionnaire) => [
                'id' => $questionnaire->id,
                'code' => $questionnaire->code,
                'name' => $questionnaire->name,
                'version' => $questionnaire->version,
                'status' => $questionnaire->status,
                'description' => $questionnaire->description,
                'recall_period_days' => $questionnaire->recall_period_days,
                'questions_count' => $questionnaire->questions_count,
                'ranges_count' => $questionnaire->ranges_count,
                'disclaimer' => $questionnaire->disclaimer,
                'is_available' => $questionnaire->isAvailableForApplication(),
                'unavailable_reason' => $questionnaire->isAvailableForApplication()
                    ? null
                    : ($questionnaire->status === ScreeningQuestionnaire::STATUS_RETIRED
                        ? 'Esta versão foi descontinuada e permanece disponível apenas no histórico.'
                        : 'Aguardando aprovação e ativação por profissional habilitado.'),
                'approved_by_name' => $questionnaire->approved_by_name,
                'approved_profession' => $questionnaire->approved_profession,
                'professional_registration' => $questionnaire->professional_registration,
                'approved_at' => $questionnaire->approved_at?->toIso8601String(),
            ]);

        $sessionsQuery = $client->screeningSessions();
        if (! $request->user()->isAdmin()) {
            $sessionsQuery->where('requested_by_user_id', $request->user()->id);
        }

        $sessions = $sessionsQuery
            ->latest()
            ->get();

        return response()->json([
            'questionnaires' => $questionnaires,
            'sessions' => $this->history->present($sessions),
            'defaults' => ['expires_in_minutes' => 1440, 'access_mode' => 'remote'],
        ]);
    }

    public function store(StoreScreeningInvitationRequest $request): JsonResponse
    {
        return response()->json([
            'invitation' => $this->invitations->create($request, $request->validated()),
        ], 201);
    }

    public function resend(ResendScreeningInvitationRequest $request, ScreeningSession $session): JsonResponse
    {
        return response()->json([
            'invitation' => $this->invitations->resend($request, $session, $request->integer('expires_in_minutes') ?: null),
        ]);
    }

    public function revoke(Request $request, ScreeningSession $session): JsonResponse
    {
        return response()->json([
            'session' => $this->invitations->revoke($request, $session),
        ]);
    }
}
