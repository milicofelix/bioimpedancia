<?php

namespace App\Services\Screening;

use App\Models\AppAudit;
use App\Models\Screening\ScreeningSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ScreeningResultPdfService
{
    public function stream(Request $request, ScreeningSession $session): Response
    {
        $session->loadMissing(['answers', 'resultRange']);

        AppAudit::query()->create([
            'user_id' => null,
            'action' => 'screening.result_pdf.opened',
            'auditable_type' => ScreeningSession::class,
            'auditable_id' => $session->id,
            'description' => 'Resultado público da triagem aberto em PDF',
            'new_values' => [
                'questionnaire_code' => $session->questionnaire_code,
                'questionnaire_version' => $session->questionnaire_version,
                'scoring_rules_version' => $session->scoring_rules_version,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        config(['dompdf.public_path' => public_path()]);

        $pdf = Pdf::loadView('screening.result-pdf', [
            'screeningSession' => $session,
            'hasAttentionAnswers' => $session->answers->contains('requires_individual_attention', true),
            'issuedAt' => now(),
        ])->setPaper('a4');

        $response = $pdf->stream('resultado-triagem-vita-acea.pdf');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
