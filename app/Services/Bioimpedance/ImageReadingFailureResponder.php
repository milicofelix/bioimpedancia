<?php

namespace App\Services\Bioimpedance;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ImageReadingFailureResponder
{
    public function respond(ConnectionException|RequestException $exception): JsonResponse
    {
        if ($exception instanceof ConnectionException) {
            $timedOut = str_contains(mb_strtolower($exception->getMessage()), 'timed out');

            Log::warning('bioimpedance.image_reading_unavailable', [
                'reason' => $timedOut ? 'timeout' : 'connection',
            ]);

            return response()->json([
                'message' => $timedOut
                    ? 'A leitura do relatório excedeu o tempo esperado. A imagem não foi salva; tente novamente.'
                    : 'Não foi possível acessar o serviço de leitura da imagem. Tente novamente.',
            ], $timedOut ? 504 : 502);
        }

        $response = $exception->response;
        $code = $this->safeIdentifier($response->json('error.code'));
        $type = $this->safeIdentifier($response->json('error.type'));

        // Never log the exception, response body or request: they may contain clinical data or credentials.
        Log::warning('bioimpedance.image_reading_rejected', [
            'provider_status' => $response->status(),
            'provider_error_code' => $code,
            'provider_error_type' => $type,
            'provider_request_id' => $this->safeIdentifier($response->header('x-request-id')),
        ]);

        if ($type === 'insufficient_quota' || in_array($code, [
            'insufficient_quota',
            'credit_balance_exhausted',
            'billing_hard_limit_reached',
            'organization_spend_limit_exceeded',
            'project_spend_limit_exceeded',
            'organization_usage_limit_exceeded',
        ], true)) {
            return response()->json([
                'message' => 'A leitura de imagens está indisponível por falta de créditos ou limite de uso do serviço. Solicite ao administrador a revisão do saldo e dos limites da API antes de tentar novamente.',
            ], 503);
        }

        if ($response->status() === 429) {
            return response()->json([
                'message' => 'O serviço de leitura atingiu o limite temporário de solicitações. Aguarde alguns instantes antes de tentar novamente.',
            ], 503);
        }

        if (in_array($response->status(), [401, 403], true)) {
            return response()->json([
                'message' => 'O acesso ao serviço de leitura não foi autorizado. Solicite ao administrador a revisão da configuração da integração.',
            ], 503);
        }

        return response()->json([
            'message' => $response->serverError()
                ? 'O serviço de leitura está temporariamente indisponível. Tente novamente em alguns instantes.'
                : 'O serviço de leitura recusou o processamento. Solicite ao administrador a verificação da integração antes de tentar novamente.',
        ], 502);
    }

    private function safeIdentifier(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[A-Za-z0-9_.-]{1,128}\z/', $value) === 1
            ? $value
            : null;
    }
}
