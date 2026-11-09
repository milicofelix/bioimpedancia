<?php

namespace Tests\Feature;

use App\Models\AiUsageRecord;
use App\Models\User;
use App\Services\Ai\OpenAiResponsesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiUsageReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_external_request_is_recorded_without_sensitive_content(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['*/responses' => Http::response([
            'model' => 'gpt-6.1-terra', 'service_tier' => 'default',
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 100],
            'output' => [['text' => 'clinical-response-not-to-store']],
        ])]);
        $client = app(OpenAiResponsesClient::class);
        foreach (['bodyscan_ocr', 'bodyscan_segmental_ocr', 'body_observation', 'care_guidance', 'relaxmedic_ocr'] as $operation) {
            $client->send($operation, ['model' => 'gpt-6.1-terra', 'input' => 'clinical-input-not-to-store'])->throw();
        }
        $this->assertDatabaseCount('ai_usage_records', 5);
        Http::assertSentCount(5);
        $record = AiUsageRecord::query()->firstOrFail();
        $this->assertSame('estimated', $record->cost_status);
        $this->assertSame('0.001500000000', $record->estimated_cost_usd);
        $serialized = AiUsageRecord::all()->toJson();
        foreach (['clinical-response-not-to-store', 'clinical-input-not-to-store', 'test-key'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $serialized);
        }
    }

    public function test_http_failure_is_recorded_before_the_existing_exception_is_thrown(): void
    {
        Http::fake(['*/responses' => Http::response(['error' => ['message' => 'not-to-store']], 429)]);
        try {
            app(OpenAiResponsesClient::class)->send('care_guidance', ['model' => 'gpt-6.1-terra'])->throw();
            $this->fail('Expected the original HTTP exception.');
        } catch (RequestException) {
            $this->assertDatabaseHas('ai_usage_records', [
                'http_status' => 429, 'cost_status' => 'usage_unavailable', 'estimated_cost_usd' => null,
            ]);
        }
        Http::assertSentCount(1);
    }

    public function test_connection_failure_preserves_unknown_consumption_and_does_not_retry(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('sensitive-network-error');
        });
        try {
            app(OpenAiResponsesClient::class)->send('bodyscan_ocr', ['model' => 'gpt-6.1-terra']);
            $this->fail('Expected connection failure.');
        } catch (ConnectionException) {
            $this->assertSame(1, $calls);
            $this->assertDatabaseHas('ai_usage_records', [
                'outcome' => 'connection_unknown', 'cost_status' => 'usage_unavailable', 'estimated_cost_usd' => null,
            ]);
        }
    }

    public function test_accounting_failure_does_not_interrupt_or_repeat_a_paid_response(): void
    {
        Log::spy();
        AiUsageRecord::creating(fn () => throw new \RuntimeException('do-not-log-this'));
        Http::fake(['*/responses' => Http::response(['usage' => ['input_tokens' => 1000, 'output_tokens' => 100]])]);
        try {
            $response = app(OpenAiResponsesClient::class)->send('body_observation', ['model' => 'gpt-6.1-terra']);
            $this->assertSame(200, $response->status());
            Http::assertSentCount(1);
            Log::shouldHaveReceived('warning')->once()->with('ai_usage_recording_failed', ['operation' => 'body_observation']);
        } finally {
            AiUsageRecord::flushEventListeners();
        }
    }

    public function test_report_is_admin_only_and_validates_dates(): void
    {
        $url = '/bioimpedance/ai-usage?from=2026-11-01&to=2026-11-08';
        $this->getJson($url)->assertUnauthorized();
        foreach ([User::ROLE_PROFESSIONAL, User::ROLE_RECEPTION, User::ROLE_VIEWER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson($url)->assertForbidden();
        }
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->getJson($url)->assertOk()->assertJsonPath('summary.requests', 0);
        $this->getJson('/bioimpedance/ai-usage?from=wrong&to=2026-11-08')->assertUnprocessable();
        $this->getJson('/bioimpedance/ai-usage?from=2024-01-01&to=2026-11-08')->assertUnprocessable();
        $this->getJson('/bioimpedance/ai-usage?from=2026-11-08&to=2026-11-01')->assertUnprocessable();
    }

    public function test_period_totals_include_all_pages_and_survive_reloading(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->startOfDay());
        $responses = Http::sequence();
        for ($index = 0; $index < 21; $index++) {
            $responses->push(['usage' => ['input_tokens' => 1000, 'output_tokens' => 100]]);
        }
        $responses->push([], 502);
        Http::fake(['*/responses' => $responses]);
        $client = app(OpenAiResponsesClient::class);
        for ($index = 0; $index < 21; $index++) {
            $client->send('body_observation', ['model' => 'gpt-6.1-terra']);
        }
        $client->send('care_guidance', ['model' => 'gpt-6.1-terra']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $url = '/bioimpedance/ai-usage?from=2026-11-08&to=2026-11-08';
        $first = $this->getJson($url)->assertOk()->assertJsonPath('summary.requests', 22)
            ->assertJsonPath('summary.unpriced_requests', 1)->assertJsonPath('records.last_page', 2);
        $this->assertEqualsWithDelta(0.0315, (float) $first->json('summary.estimated_usd'), 0.000000001);
        $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(2, 'records.data')->assertJsonPath('summary.requests', 22);
        $this->getJson($url.'&operation=care_guidance')->assertOk()->assertJsonPath('summary.requests', 1)
            ->assertJsonPath('summary.estimated_usd', null);
        $this->getJson('/bioimpedance/ai-usage?from=2026-11-07&to=2026-11-07')->assertOk()->assertJsonPath('summary.requests', 0);
    }
}
