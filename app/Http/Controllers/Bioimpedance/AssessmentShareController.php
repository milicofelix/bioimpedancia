<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceReportShare;
use App\Services\Bioimpedance\BioimpedanceWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssessmentShareController extends Controller
{
    public function __construct(private readonly BioimpedanceWorkflow $workflow) {}

    public function store(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        return $this->workflow->createAssessmentShare($request, $assessment);
    }

    public function revoke(Request $request, BioimpedanceReportShare $share): JsonResponse
    {
        return $this->workflow->revokeAssessmentShare($request, $share);
    }

    public function publicReport(Request $request, string $token): Response
    {
        return $this->workflow->publicReport($request, $token);
    }
}
