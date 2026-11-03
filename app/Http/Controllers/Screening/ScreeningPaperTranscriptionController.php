<?php

namespace App\Http\Controllers\Screening;

use App\Domain\Screening\CurrentAnxietyQuestionnaire;
use App\Domain\Screening\VitaAceaQuestionnaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Screening\CompletePaperScreeningRequest;
use App\Http\Requests\Screening\StorePaperScreeningRequest;
use App\Models\Screening\ScreeningSession;
use App\Services\Screening\ScreeningHistoryPresenter;
use App\Services\Screening\ScreeningPaperTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScreeningPaperTranscriptionController extends Controller
{
    public function __construct(private readonly ScreeningPaperTranscriptionService $transcriptions) {}

    public function store(StorePaperScreeningRequest $request, ScreeningHistoryPresenter $history): JsonResponse
    {
        $session = $this->transcriptions->create($request, $request->validated());

        return response()->json(['application' => $history->present(collect([$session]))[0]], 201)->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, ScreeningSession $session): Response
    {
        $this->transcriptions->authorize($request, $session);
        $session->loadMissing(['questionnaire.questions.options', 'client', 'answers']);
        $visualAnswer = $session->answers->firstWhere('question_code', VitaAceaQuestionnaire::VISUAL_QUESTION_CODE);

        return response()->view('screening.public-access', [
            'internalTranscription' => true,
            'screeningSession' => $session,
            'identityVerified' => true,
            'completed' => $session->status === ScreeningSession::STATUS_FINALIZED,
            'visualChoiceResult' => $visualAnswer ? VitaAceaQuestionnaire::visualResult((int) $visualAnswer->response_value) : null,
            'isScoredQuestionnaire' => $session->questionnaire_code !== CurrentAnxietyQuestionnaire::CODE,
        ], 200, [
            'Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }

    public function complete(CompletePaperScreeningRequest $request, ScreeningSession $session): RedirectResponse
    {
        $this->transcriptions->complete($request, $session, $request->validated());

        return redirect()->route('screening.paper.show', $session)->with('screening_completed', true);
    }

    public function cancel(Request $request, ScreeningSession $session, ScreeningHistoryPresenter $history): JsonResponse
    {
        $this->transcriptions->authorize($request, $session);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $cancelled = $this->transcriptions->cancel($request, $session, $data['reason']);

        return response()->json(['session' => $history->present(collect([$cancelled]))[0]])->header('Cache-Control', 'no-store, private');
    }
}
