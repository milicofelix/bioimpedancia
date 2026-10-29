<?php

namespace App\Http\Controllers\Screening;

use App\Http\Controllers\Controller;
use App\Http\Requests\Screening\ApproveScreeningQuestionnaireRequest;
use App\Models\Screening\ScreeningQuestionnaire;
use App\Services\Screening\ScreeningQuestionnaireApprovalService;
use Illuminate\Http\JsonResponse;

class ScreeningQuestionnaireApprovalController extends Controller
{
    public function __invoke(
        ApproveScreeningQuestionnaireRequest $request,
        ScreeningQuestionnaire $questionnaire,
        ScreeningQuestionnaireApprovalService $approvals,
    ): JsonResponse {
        $approved = $approvals->approve($request, $questionnaire, $request->validated());

        return response()->json([
            'questionnaire' => [
                'id' => $approved->id,
                'code' => $approved->code,
                'name' => $approved->name,
                'version' => $approved->version,
                'status' => $approved->status,
                'is_available' => $approved->isAvailableForApplication(),
                'unavailable_reason' => null,
                'approved_by_name' => $approved->approved_by_name,
                'approved_profession' => $approved->approved_profession,
                'professional_registration' => $approved->professional_registration,
                'approved_at' => $approved->approved_at?->toIso8601String(),
            ],
        ]);
    }
}
