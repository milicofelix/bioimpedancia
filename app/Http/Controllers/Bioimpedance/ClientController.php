<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Services\Bioimpedance\BioimpedanceWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function __construct(private readonly BioimpedanceWorkflow $workflow) {}

    public function store(Request $request): JsonResponse
    {
        return $this->workflow->storeClient($request);
    }

    public function update(Request $request, BioimpedanceClient $client): JsonResponse
    {
        return $this->workflow->updateClient($request, $client);
    }

    public function inactivate(Request $request, BioimpedanceClient $client): JsonResponse
    {
        return $this->workflow->inactivateClient($request, $client);
    }

    public function exportPrivacyData(Request $request, BioimpedanceClient $client): Response
    {
        return $this->workflow->exportClientPrivacyData($request, $client);
    }

    public function anonymize(Request $request, BioimpedanceClient $client): JsonResponse
    {
        return $this->workflow->anonymizeClient($request, $client);
    }
}
