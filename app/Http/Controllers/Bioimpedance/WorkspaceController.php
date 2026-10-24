<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(private readonly BioimpedanceController $workflow) {}

    public function index(Request $request): JsonResponse
    {
        return $this->workflow->index($request);
    }

    public function operational(Request $request): JsonResponse
    {
        return $this->workflow->operationalIndex($request);
    }
}
