<?php

namespace App\Http\Controllers\Screening;

use App\Http\Controllers\Controller;
use App\Http\Requests\Screening\ResendScreeningInvitationRequest;
use App\Http\Requests\Screening\StoreScreeningInvitationRequest;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Screening\ScreeningQuestionnaire;
use App\Models\Screening\ScreeningSession;
use App\Services\Screening\ScreeningInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningInvitationController extends Controller
{
    public function __construct(private readonly ScreeningInvitationService $invitations) {}

    public function index(Request $request, BioimpedanceClient $client): JsonResponse
    {
        abort_unless($request->user()?->isClinicalProfessional() && $request->user()->isActive(), 403);

        $questionnaires = ScreeningQuestionnaire::query()
            ->orderBy('name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (ScreeningQuestionnaire $questionnaire) => [
                'id' => $questionnaire->id,
                'name' => $questionnaire->name,
                'version' => $questionnaire->version,
                'status' => $questionnaire->status,
                'is_available' => $questionnaire->isAvailableForApplication(),
                'unavailable_reason' => $questionnaire->isAvailableForApplication()
                    ? null
                    : 'Aguardando aprovação e ativação por profissional habilitado.',
            ]);

        $sessionsQuery = $client->screeningSessions();
        if (! $request->user()->isAdmin()) {
            $sessionsQuery->where('requested_by_user_id', $request->user()->id);
        }

        $sessions = $sessionsQuery
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ScreeningSession $session) => $this->invitations->sessionPayload($session));

        return response()->json([
            'questionnaires' => $questionnaires,
            'sessions' => $sessions,
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
