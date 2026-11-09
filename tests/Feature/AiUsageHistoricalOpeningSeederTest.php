<?php

namespace Tests\Feature;

use App\Models\AiUsageRecord;
use App\Models\User;
use Database\Seeders\AiUsageHistoricalOpeningSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiUsageHistoricalOpeningSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_idempotent_and_does_not_invent_request_details(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->startOfDay());
        $this->seed(AiUsageHistoricalOpeningSeeder::class);
        $record = AiUsageRecord::query()->sole();
        $createdAt = $record->created_at->toIso8601String();

        $this->assertSame('1.500000000000', $record->estimated_cost_usd);
        $this->assertSame(AiUsageRecord::HISTORICAL_OPENING_OPERATION, $record->operation);
        $this->assertSame('historical_reported', $record->cost_status);
        $this->assertNull($record->price_snapshot);
        foreach (['input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens', 'cache_write_30m_tokens', 'cache_write_12h_tokens', 'http_status', 'service_tier'] as $field) {
            $this->assertNull($record->{$field});
        }

        $this->travel(1)->days();
        $this->seed(AiUsageHistoricalOpeningSeeder::class);
        $this->assertDatabaseCount('ai_usage_records', 1);
        $this->assertSame($createdAt, $record->fresh()->created_at->toIso8601String());
        $this->assertSame('1.500000000000', $record->fresh()->estimated_cost_usd);
    }

    public function test_report_adds_history_without_counting_it_as_a_request_or_unknown_cost(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->startOfDay());
        $existing = AiUsageRecord::query()->create([
            'operation' => 'body_observation', 'model' => 'existing-model',
            'outcome' => 'response_received', 'duration_ms' => 1000,
            'input_tokens' => 100, 'output_tokens' => 50,
            'cost_status' => 'estimated', 'estimated_cost_usd' => '0.020000000000',
        ]);
        AiUsageRecord::query()->create([
            'operation' => 'care_guidance', 'model' => 'existing-model',
            'outcome' => 'connection_unknown', 'duration_ms' => 1000,
            'cost_status' => 'usage_unavailable', 'estimated_cost_usd' => null,
        ]);
        $this->seed(AiUsageHistoricalOpeningSeeder::class);
        $this->assertSame('0.020000000000', $existing->fresh()->estimated_cost_usd);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $url = '/bioimpedance/ai-usage?from=2026-11-08&to=2026-11-08';
        $report = $this->getJson($url)->assertOk()
            ->assertJsonPath('summary.requests', 2)
            ->assertJsonPath('summary.unpriced_requests', 1)
            ->assertJsonPath('summary.historical_records', 1)
            ->assertJsonPath('summary.input_tokens', 100)
            ->assertJsonPath('summary.output_tokens', 50);
        $this->assertEqualsWithDelta(1.52, (float) $report->json('summary.estimated_usd'), 0.000000001);
        $this->assertEqualsWithDelta(1.50, (float) $report->json('summary.historical_usd'), 0.000000001);

        $this->getJson($url.'&operation=historical_opening')->assertOk()
            ->assertJsonPath('summary.requests', 0)
            ->assertJsonPath('summary.unpriced_requests', 0)
            ->assertJsonPath('summary.historical_records', 1)
            ->assertJsonPath('records.total', 1);
        $this->getJson($url.'&operation=body_observation')->assertOk()
            ->assertJsonPath('summary.requests', 1)
            ->assertJsonPath('summary.historical_records', 0);
    }

    public function test_inclusion_date_is_not_backdated_to_an_unknown_consumption_period(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->startOfDay());
        $this->seed(AiUsageHistoricalOpeningSeeder::class);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->getJson('/bioimpedance/ai-usage?from=2026-11-07&to=2026-11-07')->assertOk()
            ->assertJsonPath('summary.historical_records', 0)
            ->assertJsonPath('records.total', 0);
    }
}
