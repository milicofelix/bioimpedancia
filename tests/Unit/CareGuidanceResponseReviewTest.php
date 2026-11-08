<?php

namespace Tests\Unit;

use App\Models\Bioimpedance\BioimpedanceKnowledgeChunk;
use App\Services\Bioimpedance\BioimpedanceCareGuidanceAssistant;
use App\Services\Bioimpedance\CareGuidanceResponseReview;
use App\Services\Bioimpedance\ContextualCareEducation;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\BodyCompositionProfiles;
use Tests\TestCase;

class CareGuidanceResponseReviewTest extends TestCase
{
    public function test_safe_external_response_is_preserved_without_silent_replacement(): void
    {
        $context = BodyCompositionProfiles::context(false);
        $payload = $this->payload($context);
        $review = app(CareGuidanceResponseReview::class)->review($this->generation($payload), $this->chunks(), $context, function () {
            $this->fail('Uma resposta externa válida não deve ser substituída.');
        });
        $this->assertSame('openai', $review['generation']['provider']);
        $this->assertFalse($review['generation']['fallback_used']);
        $this->assertSame($payload, $review['generation']['payload']);
        $this->assertSame([], $review['validation']['errors']);
    }

    public function test_unsafe_response_preserves_external_errors_and_validates_the_fallback(): void
    {
        $context = BodyCompositionProfiles::context(true);
        $unsafe = $this->payload($context);
        $unsafe['claims'][0]['text'] = 'Prescrevo uma dieta individualizada de emergência.';
        $unsafe['claims'][0]['requires_professional_review'] = false;
        $safe = $this->payload($context);
        $review = app(CareGuidanceResponseReview::class)->review($this->generation($unsafe), $this->chunks(), $context, fn () => $safe);
        $this->assertSame('local_reference_engine', $review['generation']['provider']);
        $this->assertSame('openai', $review['generation']['attempted_provider']);
        $this->assertTrue($review['generation']['fallback_used']);
        $this->assertNotEmpty($review['generation']['fallback_reason']);
        $this->assertSame([], $review['validation']['errors']);
        $this->assertNotEmpty($review['external_errors']);
        $this->assertSame($unsafe, $review['external_payload']);
        $this->assertContains('A revisão profissional obrigatória não foi sinalizada.', $review['blocked_claims'][0]['blocked_reasons']);
        $this->assertStringNotContainsString('Prescrevo', collect($review['validation']['accepted_claims'])->pluck('text')->implode(' '));
    }

    public function test_invalid_local_fallback_stays_blocked_instead_of_bypassing_safety(): void
    {
        $context = BodyCompositionProfiles::context(false);
        $review = app(CareGuidanceResponseReview::class)->review($this->generation(['claims' => []]), $this->chunks(), $context, fn () => ['claims' => []]);
        $this->assertNotEmpty($review['validation']['errors']);
        $this->assertSame([], $review['validation']['accepted_claims']);
        $this->assertNotEmpty($review['external_errors']);
    }

    public function test_http_failure_has_explicit_provider_and_fallback_metadata_without_a_paid_call(): void
    {
        config(['bioimpedance.assistant.provider' => 'hybrid', 'services.openai.api_key' => 'fake-test-key']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['error' => ['code' => 'insufficient_quota']], 429)]);
        $assistant = app(BioimpedanceCareGuidanceAssistant::class);
        $context = [...BodyCompositionProfiles::context(false), 'attention_points' => [], 'prioritized_findings' => []];
        $generation = (new \ReflectionMethod($assistant, 'generate'))->invoke($assistant, $context, $this->chunks());
        $this->assertSame('local_reference_engine', $generation['provider']);
        $this->assertSame('openai', $generation['attempted_provider']);
        $this->assertTrue($generation['fallback_used']);
        $this->assertNotEmpty($generation['fallback_reason']);
        $review = app(CareGuidanceResponseReview::class)->review($generation, $this->chunks(), $context, fn () => []);
        $this->assertSame([], $review['validation']['errors']);
        Http::assertSentCount(1);
    }

    private function generation(array $payload): array
    {
        return ['payload' => $payload, 'provider' => 'openai', 'attempted_provider' => 'openai', 'fallback_used' => false, 'fallback_reason' => null];
    }

    private function payload(array $context): array
    {
        return ['claims' => array_map(fn ($claim) => [
            ...$claim,
            'finding_ids' => array_values(array_intersect($claim['source_ids'], array_column($context['findings'], 'id'))),
            'comparison_scope' => 'none',
        ], (new ContextualCareEducation)->claims($context))];
    }

    private function chunks()
    {
        return collect([
            ['brazil-food-guide-natural-foods', ['food']], ['brazil-food-guide-ultraprocessed', ['food']],
            ['who-adult-physical-activity', ['physical_activity']], ['vita-scan-care-guidance-safety', ['safety']],
        ])->map(fn ($chunk) => new BioimpedanceKnowledgeChunk(['chunk_key' => $chunk[0], 'tags' => $chunk[1]]));
    }
}
