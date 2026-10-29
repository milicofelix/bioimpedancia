<?php

namespace App\Http\Controllers\Screening;

use App\Http\Controllers\Controller;
use App\Models\Screening\ScreeningSession;
use App\Services\Screening\ScreeningResultPdfService;
use App\Services\Screening\ScreeningSessionAccessAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScreeningResultPdfController extends Controller
{
    public function __invoke(
        Request $request,
        ScreeningSession $session,
        ScreeningSessionAccessAuthorizer $access,
        ScreeningResultPdfService $pdfService,
    ): Response {
        $access->authorize($request, $session);
        abort_unless($session->status === ScreeningSession::STATUS_FINALIZED, 404);

        return $pdfService->stream($request, $session, 'screening.result_pdf.downloaded');
    }
}
