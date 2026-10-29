<?php

namespace App\Http\Controllers\Screening;

use App\Http\Controllers\Controller;
use App\Http\Requests\Screening\UpdateScreeningProfessionalReviewRequest;
use App\Models\Screening\ScreeningSession;
use App\Services\Screening\ScreeningProfessionalReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningProfessionalReviewController extends Controller
{
    public function __construct(private readonly ScreeningProfessionalReviewService $reviews) {}

    public function show(Request $request, ScreeningSession $session): JsonResponse
    {
        return response()->json(['session' => $this->reviews->details($request, $session)]);
    }

    public function update(UpdateScreeningProfessionalReviewRequest $request, ScreeningSession $session): JsonResponse
    {
        return response()->json([
            'session' => $this->reviews->update($request, $session, $request->validated()),
        ]);
    }
}
