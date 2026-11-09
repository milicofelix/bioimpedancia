<?php

namespace App\Services\Ai;

use App\Models\AiUsageRecord;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiResponsesClient
{
    public function __construct(private readonly OpenAiUsageCost $cost) {}

    public function send(string $operation, array $payload, ?int $timeout = null): Response
    {
        $started = hrtime(true);
        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->acceptJson()->asJson()->connectTimeout(10)
                ->timeout($timeout ?? (int) config('services.openai.timeout', 45))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', $payload);
        } catch (ConnectionException $exception) {
            $this->record($operation, $payload, $started, null);
            throw $exception;
        }

        // Record before callers throw on HTTP errors or reject content semantically.
        $this->record($operation, $payload, $started, $response);

        return $response;
    }

    private function record(string $operation, array $payload, int $started, ?Response $response): void
    {
        try {
            $data = $response?->json();
            $usage = is_array($data['usage'] ?? null) ? $data['usage'] : null;
            $model = is_string($data['model'] ?? null) ? $data['model'] : (string) ($payload['model'] ?? 'unknown');
            $tier = is_string($data['service_tier'] ?? null) ? $data['service_tier'] : (string) ($payload['service_tier'] ?? 'default');
            $count = static fn (mixed $value) => is_int($value) && $value >= 0 ? $value : null;

            AiUsageRecord::create([
                'operation' => $operation,
                'model' => mb_substr($model, 0, 120),
                'service_tier' => mb_substr($tier, 0, 30),
                'outcome' => $response === null ? 'connection_unknown' : ($response->successful() ? 'response_received' : 'http_error'),
                'http_status' => $response?->status(),
                'duration_ms' => (int) round((hrtime(true) - $started) / 1000000),
                'input_tokens' => $count($usage['input_tokens'] ?? null),
                'output_tokens' => $count($usage['output_tokens'] ?? null),
                'cached_tokens' => $count($usage['input_tokens_details']['cached_tokens'] ?? null),
                'reasoning_tokens' => $count($usage['output_tokens_details']['reasoning_tokens'] ?? null),
                'cache_write_30m_tokens' => $count($usage['input_tokens_details']['cache_write_tokens_details']['ttl_30m_tokens'] ?? null),
                'cache_write_12h_tokens' => $count($usage['input_tokens_details']['cache_write_tokens_details']['ttl_12h_tokens'] ?? null),
                ...$this->cost->estimate($model, $usage, $tier),
            ]);
        } catch (Throwable) {
            // Accounting failure must not repeat a paid request or interrupt care.
            // Never log exception messages: DB/HTTP exceptions may contain secrets or clinical text.
            Log::warning('ai_usage_recording_failed', ['operation' => $operation]);
        }
    }
}
