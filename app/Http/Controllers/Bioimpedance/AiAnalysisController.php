<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Services\Bioimpedance\BioimpedanceCareGuidanceAssistant;
use App\Services\Bioimpedance\BioimpedanceWorkflow;
use App\Services\Bioimpedance\BioimpedanceObservationAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAnalysisController extends Controller
{
    public function __construct(
        private readonly BioimpedanceWorkflow $workflow,
        private readonly BioimpedanceObservationAssistant $observationAssistant,
        private readonly BioimpedanceCareGuidanceAssistant $careGuidanceAssistant,
    ) {}

    public function suggestObservation(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        return $this->workflow->suggestAssessmentObservation($request, $assessment, $this->observationAssistant);
    }

    public function approveObservation(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        return $this->workflow->approveAssessmentObservation($request, $assessment);
    }

    public function suggestCareGuidance(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        return $this->workflow->suggestAssessmentCareGuidance($request, $assessment, $this->careGuidanceAssistant);
    }

    public function approveCareGuidance(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        return $this->workflow->approveAssessmentCareGuidance($request, $assessment);
    }
}
