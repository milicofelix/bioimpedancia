<?php

namespace App\Services\Bioimpedance;

use Closure;
use Illuminate\Support\Collection;

class CareGuidanceResponseReview
{
    public function __construct(private readonly CareGuidanceSemanticValidator $validator) {}

    public function review(array $generation, Collection $chunks, array $context, Closure $localResponse): array
    {
        $validation = $this->validator->validate($generation['payload'], $chunks, $context);
        $externalPayload = $generation['provider'] === 'openai' ? $generation['payload'] : null;
        $externalErrors = [];
        $blockedClaims = [];
        if ($validation['errors'] && $generation['provider'] === 'openai') {
            $externalErrors = $validation['errors'];
            $blockedClaims = $validation['blocked_claims'];
            $generation = [
                'payload' => $localResponse(),
                'provider' => 'local_reference_engine',
                'attempted_provider' => 'openai',
                'fallback_used' => true,
                'fallback_reason' => 'A resposta externa foi bloqueada pela validação semântica.',
            ];
            $validation = $this->validator->validate($generation['payload'], $chunks, $context);
        }

        return [
            'generation' => $generation,
            'validation' => $validation,
            'external_payload' => $externalPayload,
            'external_errors' => $externalErrors,
            'blocked_claims' => [...$blockedClaims, ...$validation['blocked_claims']],
        ];
    }
}
