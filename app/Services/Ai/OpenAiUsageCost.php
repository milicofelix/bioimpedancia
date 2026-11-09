<?php

namespace App\Services\Ai;

class OpenAiUsageCost
{
    public function estimate(string $model, ?array $usage, string $tier): array
    {
        if ($usage === null || ! $this->validCount($usage['input_tokens'] ?? null)
            || ! $this->validCount($usage['output_tokens'] ?? null)) {
            return $this->unavailable('usage_unavailable');
        }

        $catalog = config('ai_usage.prices', []);
        // Only exact names and official dated snapshots, not similar model names.
        $baseModel = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model);
        $rates = $catalog[$model] ?? $catalog[$baseModel] ?? null;
        if ($rates === null || ! in_array($tier, ['default', 'auto'], true)) {
            return $this->unavailable('price_unavailable');
        }

        $input = $usage['input_tokens'];
        $output = $usage['output_tokens'];
        if ($input > ($rates['long_context_threshold'] ?? PHP_INT_MAX)) {
            $rates = $rates['long_context'];
        }
        unset($rates['long_context'], $rates['long_context_threshold']);

        $details = $usage['input_tokens_details'] ?? [];
        if (! is_array($details) || (isset($details['cache_write_tokens_details']) && ! is_array($details['cache_write_tokens_details']))) {
            return $this->unavailable('usage_invalid');
        }
        $cached = $details['cached_tokens'] ?? 0;
        $writes = $details['cache_write_tokens'] ?? 0;
        $shortWrites = $details['cache_write_tokens_details']['ttl_30m_tokens'] ?? 0;
        $longWrites = $details['cache_write_tokens_details']['ttl_12h_tokens'] ?? 0;
        foreach ([$cached, $writes, $shortWrites, $longWrites] as $count) {
            if (! $this->validCount($count)) {
                return $this->unavailable('usage_invalid');
            }
        }
        if ($cached + $writes > $input || $shortWrites + $longWrites !== $writes) {
            return $this->unavailable('usage_invalid');
        }
        if ($writes > 0 && ! isset($rates['write_30m'], $rates['write_12h'])) {
            return $this->unavailable('price_unavailable');
        }

        $cost = (($input - $cached - $writes) * $rates['input']
            + $cached * $rates['cached'] + $output * $rates['output']
            + $shortWrites * ($rates['write_30m'] ?? 0)
            + $longWrites * ($rates['write_12h'] ?? 0)) / 1000000;

        return [
            'cost_status' => 'estimated',
            'estimated_cost_usd' => round($cost, 12),
            'price_snapshot' => ['version' => config('ai_usage.price_version'), 'per_million_tokens_usd' => $rates],
        ];
    }

    private function validCount(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }

    private function unavailable(string $status): array
    {
        return ['cost_status' => $status, 'estimated_cost_usd' => null, 'price_snapshot' => null];
    }
}
