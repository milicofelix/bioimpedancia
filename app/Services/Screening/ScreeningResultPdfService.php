<?php

namespace App\Services\Screening;

use App\Domain\Screening\CurrentAnxietyQuestionnaire;
use App\Domain\Screening\VitaAceaQuestionnaire;
use App\Models\AppAudit;
use App\Models\Screening\ScreeningSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ScreeningResultPdfService
{
    public function stream(
        Request $request,
        ScreeningSession $session,
        string $auditAction = 'screening.result_pdf.opened',
    ): Response
    {
        $session->loadMissing(['answers', 'client']);

        AppAudit::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $auditAction,
            'auditable_type' => ScreeningSession::class,
            'auditable_id' => $session->id,
            'description' => $auditAction === 'screening.result_pdf.downloaded'
                ? 'PDF da triagem baixado pelo profissional'
                : 'Resultado público da triagem aberto em PDF',
            'new_values' => [
                'questionnaire_code' => $session->questionnaire_code,
                'questionnaire_version' => $session->questionnaire_version,
                'scoring_rules_version' => $session->scoring_rules_version,
                'document_authenticity_code' => $session->document_authenticity_code,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        config(['dompdf.public_path' => public_path()]);

        $visualAnswer = $session->answers->firstWhere('question_code', VitaAceaQuestionnaire::VISUAL_QUESTION_CODE);
        $visualChoiceResult = $visualAnswer
            ? VitaAceaQuestionnaire::visualResult((int) $visualAnswer->response_value)
            : null;

        $pdf = Pdf::loadView('screening.result-pdf', [
            'screeningSession' => $session,
            'issuedAt' => now(),
            'visualChoiceResult' => $visualChoiceResult,
            'visualChoiceThumbnail' => $this->visualChoiceThumbnail($visualChoiceResult),
            'isScoredQuestionnaire' => $session->questionnaire_code !== CurrentAnxietyQuestionnaire::CODE,
        ])->setPaper('a4');

        $documentSlug = Str::slug($session->questionnaire_code ?: 'vita-scan');
        $response = $pdf->stream("resultado-triagem-{$documentSlug}.pdf");
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', 'sandbox');

        return $response;
    }

    /** @param array{value: int, visual_key: string, title: string, description: string}|null $visualChoiceResult */
    private function visualChoiceThumbnail(?array $visualChoiceResult): ?string
    {
        if (! $visualChoiceResult) {
            return null;
        }

        $svg = view('screening.partials.visual-choice-symbol', [
            'visualChoiceResult' => $visualChoiceResult,
        ])->render();

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
