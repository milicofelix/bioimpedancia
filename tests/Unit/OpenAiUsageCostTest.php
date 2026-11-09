<?php

namespace Tests\Unit;

use App\Services\Ai\OpenAiUsageCost;
use Tests\TestCase;

class OpenAiUsageCostTest extends TestCase
{
    public function test_cached_input_and_reasoning_are_not_charged_twice(): void
    {
        $result = app(OpenAiUsageCost::class)->estimate('gpt-6.1-terra-2026-11-01', [
            'input_tokens' => 10000,
            'input_tokens_details' => ['cached_tokens' => 2000],
            'output_tokens' => 1000,
            'output_tokens_details' => ['reasoning_tokens' => 800],
        ], 'default');

        $this->assertSame('estimated', $result['cost_status']);
        $this->assertEqualsWithDelta(0.0131, $result['estimated_cost_usd'], 0.000000001);
        $this->assertSame('openai-standard-2026-11-08', $result['price_snapshot']['version']);
    }

    public function test_both_cache_write_lifetimes_are_counted_separately(): void
    {
        $result = app(OpenAiUsageCost::class)->estimate('gpt-6.1-terra', [
            'input_tokens' => 12000, 'output_tokens' => 1000,
            'input_tokens_details' => [
                'cached_tokens' => 4000, 'cache_write_tokens' => 7000,
                'cache_write_tokens_details' => ['ttl_30m_tokens' => 3000, 'ttl_12h_tokens' => 4000],
            ],
        ], 'default');

        $this->assertEqualsWithDelta(0.01995, $result['estimated_cost_usd'], 0.000000001);
    }

    public function test_long_context_uses_the_corresponding_rates(): void
    {
        $result = app(OpenAiUsageCost::class)->estimate('gpt-6.1-terra', [
            'input_tokens' => 300000, 'output_tokens' => 1000,
        ], 'default');

        $this->assertEqualsWithDelta(0.6075, $result['estimated_cost_usd'], 0.000000001);
    }

    public function test_unknown_prices_or_usage_never_become_zero_cost(): void
    {
        $service = app(OpenAiUsageCost::class);
        $usage = ['input_tokens' => 1000, 'output_tokens' => 100];
        foreach ([
            $service->estimate('unknown-model', $usage, 'default'),
            $service->estimate('gpt-6.1-terra', $usage, 'flex'),
            $service->estimate('gpt-6.1-terra', null, 'default'),
            $service->estimate('gpt-6.1-terra', ['input_tokens' => -1, 'output_tokens' => 100], 'default'),
            $service->estimate('gpt-6.1-terra', [...$usage, 'input_tokens_details' => ['cache_write_tokens' => 500]], 'default'),
            $service->estimate('gpt-6.1-terra', [...$usage, 'input_tokens_details' => ['cached_tokens' => 2000]], 'default'),
        ] as $result) {
            $this->assertNull($result['estimated_cost_usd']);
            $this->assertNotSame('estimated', $result['cost_status']);
        }
    }
}
