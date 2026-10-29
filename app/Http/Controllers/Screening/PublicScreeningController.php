<?php

namespace App\Http\Controllers\Screening;

use App\Domain\Screening\CurrentAnxietyQuestionnaire;
use App\Domain\Screening\VitaAceaQuestionnaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Screening\CompletePublicScreeningRequest;
use App\Http\Requests\Screening\VerifyScreeningIdentityRequest;
use App\Models\Screening\ScreeningSession;
use App\Services\Screening\ScreeningInvitationService;
use App\Services\Screening\ScreeningSessionFinalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicScreeningController extends Controller
{
    public function __construct(private readonly ScreeningInvitationService $invitations) {}

    public function show(Request $request, string $token): Response
    {
        $session = $this->invitations->findPublicSession($token);
        abort_unless($this->invitations->canOpen($request, $session), 410, 'Este convite expirou, foi revogado ou já foi utilizado.');

        if (! $session->statusIsTerminal()) {
            $this->invitations->recordAccess($session);
        }

        $visualAnswer = $session->answers->firstWhere('question_code', VitaAceaQuestionnaire::VISUAL_QUESTION_CODE);

        return response()->view('screening.public-access', [
            'screeningSession' => $session,
            'identityPrompt' => $this->invitations->identityPrompt($session),
            'identityVerified' => $this->invitations->isVerifiedInBrowser($request, $session),
            'completed' => $session->status === ScreeningSession::STATUS_FINALIZED,
            'remainingAttempts' => max(0, ScreeningInvitationService::MAX_IDENTITY_ATTEMPTS - $session->identity_attempts),
            'visualChoiceResult' => $visualAnswer
                ? VitaAceaQuestionnaire::visualResult((int) $visualAnswer->response_value)
                : null,
            'isScoredQuestionnaire' => $session->questionnaire_code !== CurrentAnxietyQuestionnaire::CODE,
        ], 200, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }

    public function verify(VerifyScreeningIdentityRequest $request, string $token): RedirectResponse
    {
        $session = $this->invitations->findPublicSession($token);
        $this->invitations->verifyIdentity($request, $session, $request->validated());

        return redirect()->to(route('screening.public.show', ['token' => $token]).'#questionario', 303);
    }

    public function complete(
        CompletePublicScreeningRequest $request,
        string $token,
        ScreeningSessionFinalizer $finalizer,
    ): RedirectResponse {
        $session = $this->invitations->findPublicSession($token);
        abort_unless($this->invitations->isVerifiedInBrowser($request, $session), 403);

        $submittedAnswers = collect($request->validated('answers'))
            ->map(fn ($optionId, $questionId) => [
                'question_id' => (int) $questionId,
                'option_id' => (int) $optionId,
            ])
            ->values()
            ->all();

        $finalizer->finalize($request, $session, $submittedAnswers);

        return redirect()
            ->route('screening.public.show', ['token' => $token])
            ->with('screening_completed', true);
    }
}
