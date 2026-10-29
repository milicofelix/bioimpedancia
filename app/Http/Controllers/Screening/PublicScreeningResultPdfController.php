<?php

namespace App\Http\Controllers\Screening;

use App\Http\Controllers\Controller;
use App\Models\Screening\ScreeningSession;
use App\Services\Screening\ScreeningInvitationService;
use App\Services\Screening\ScreeningResultPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicScreeningResultPdfController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ScreeningInvitationService $invitations,
        ScreeningResultPdfService $pdfService,
    ): Response {
        $session = $invitations->findPublicSession($token);

        abort_unless(
            $session->status === ScreeningSession::STATUS_FINALIZED
                && $invitations->canOpen($request, $session)
                && $invitations->isVerifiedInBrowser($request, $session),
            403,
        );

        return $pdfService->stream($request, $session);
    }
}
