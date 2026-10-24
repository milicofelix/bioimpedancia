<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Services\Bioimpedance\BioimpedanceAdminDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, BioimpedanceAdminDashboard $dashboard): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Apenas administradores podem executar esta ação.');

        return response()->json([
            'admin_dashboard' => $dashboard->payload(),
        ]);
    }
}
