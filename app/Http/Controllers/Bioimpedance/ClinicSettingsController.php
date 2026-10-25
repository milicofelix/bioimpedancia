<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Services\Bioimpedance\BioimpedanceWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicSettingsController extends Controller
{
    public function __construct(private readonly BioimpedanceWorkflow $workflow) {}

    public function update(Request $request): JsonResponse
    {
        return $this->workflow->updateClinicSettings($request);
    }
}
