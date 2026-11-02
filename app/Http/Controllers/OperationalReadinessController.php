<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cacheKey = null;

        try {
            $cacheKey = 'vita-scan:readiness:'.bin2hex(random_bytes(8));
            DB::select('select 1');
            Cache::put($cacheKey, 'ready', 10);
            $cacheReady = Cache::get($cacheKey) === 'ready';
            Cache::forget($cacheKey);

            if (! $cacheReady) {
                throw new \RuntimeException('Cache readiness check failed.');
            }

            return response()->json([
                'status' => 'ready',
                'checks' => ['database' => 'ok', 'cache' => 'ok'],
            ]);
        } catch (Throwable $exception) {
            if ($cacheKey !== null) {
                try {
                    Cache::forget($cacheKey);
                } catch (Throwable) {
                    // A indisponibilidade do cache já será representada pelo HTTP 503.
                }
            }
            Log::error('operational_readiness_failed', [
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'status' => 'unavailable',
            ], 503);
        }
    }
}
