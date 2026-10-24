<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Services\Bioimpedance\BioimpedanceWorkflow;
use App\Services\Bioimpedance\LegacyBioimpedanceAnalysisRefresher;
use Illuminate\Http\Response;

class AssessmentReportController extends Controller
{
    public function __construct(
        private readonly BioimpedanceWorkflow $workflow,
        private readonly LegacyBioimpedanceAnalysisRefresher $legacyAnalysisRefresher,
    ) {}

    public function download(BioimpedanceAssessment $assessment): Response
    {
        return $this->workflow->downloadAssessmentPdf($assessment, $this->legacyAnalysisRefresher);
    }
}
