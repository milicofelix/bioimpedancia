<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Services\Bioimpedance\BioimpedanceAnalyzer;
use App\Services\Bioimpedance\RelaxmedicImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly BioimpedanceController $workflow,
        private readonly BioimpedanceAnalyzer $analyzer,
        private readonly RelaxmedicImageProcessor $imageProcessor,
    ) {}

    public function store(Request $request): JsonResponse
    {
        return $this->workflow->storeAssessment($request, $this->analyzer, $this->imageProcessor);
    }

    public function update(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        return $this->workflow->updateAssessment($request, $assessment, $this->analyzer);
    }

    public function cancel(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        return $this->workflow->cancelAssessment($request, $assessment);
    }
}
