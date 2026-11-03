<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);

        Log::withContext([
            'request_id' => $requestId,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $candidate = (string) $request->header('X-Request-ID', '');

        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{7,63}\z/', $candidate) === 1
            ? $candidate
            : (string) Str::uuid();
    }
}
