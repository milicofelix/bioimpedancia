<?php

namespace Tests\Feature;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BioimpedanceAssistantHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_guidance_uses_the_current_approved_observation_and_persists_findings(): void
    {
        $assessment = $this->approvedAssessment();
        $response = $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))
            ->assertOk()->assertJsonPath('assistant.status', 'generated')
            ->assertJsonPath('assistant.provider', 'local_reference_engine');
        $output = BioimpedanceAiAnalysisOutput::query()->findOrFail($response->json('assistant.output_id'));
        $this->assertSame($assessment->notes, data_get($output->request->structured_context, 'approved_observation.text'));
        $this->assertNotEmpty(data_get($output->request->structured_context, 'approved_observation.findings'));
        $this->patchJson(route('bioimpedance.assessments.care-guidance.approve', $assessment->id), [
            'care_guidance' => $response->json('assistant.suggestion'), 'review_action' => 'approved', 'assistant_output_id' => $output->id,
        ])->assertOk();
        $this->assertNotNull($assessment->fresh()->care_guidance_approved_at);
    }

    public function test_changed_observation_blocks_new_guidance_until_reapproval(): void
    {
        $assessment = $this->approvedAssessment();
        $assessment->update(['notes' => 'Observação alterada sem a aprovação do agente.']);
        $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))
            ->assertUnprocessable()->assertJsonValidationErrors('care_guidance');
    }

    public function test_json_key_reordering_does_not_block_care_guidance_approval(): void
    {
        $assessment = $this->approvedAssessment();
        $response = $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))->assertOk();
        $output = BioimpedanceAiAnalysisOutput::query()->with('request')->findOrFail($response->json('assistant.output_id'));
        $context = $output->request->structured_context;
        $context['approved_observation'] = array_reverse($context['approved_observation'], true);
        $output->request->update(['structured_context' => $context]);

        $this->patchJson(route('bioimpedance.assessments.care-guidance.approve', $assessment->id), [
            'care_guidance' => $response->json('assistant.suggestion'), 'review_action' => 'approved', 'assistant_output_id' => $output->id,
        ])->assertOk();
        $this->assertNotNull($assessment->fresh()->care_guidance_approved_at);
    }

    public function test_report_issuance_metadata_does_not_invalidate_care_guidance(): void
    {
        $assessment = $this->approvedAssessment();
        $response = $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))->assertOk();
        $this->travel(2)->seconds();
        $assessment->update(['report_issued_at' => now(), 'report_issue_count' => 1]);

        $this->patchJson(route('bioimpedance.assessments.care-guidance.approve', $assessment->id), [
            'care_guidance' => $response->json('assistant.suggestion'), 'review_action' => 'approved',
            'assistant_output_id' => $response->json('assistant.output_id'),
        ])->assertOk();
        $this->assertNotNull($assessment->fresh()->care_guidance_approved_at);
    }

    public function test_changed_measurement_blocks_guidance_approval_even_with_same_timestamp(): void
    {
        $assessment = $this->approvedAssessment();
        $response = $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))->assertOk();
        $assessment->update(['weight_kg' => 90]);
        $this->patchJson(route('bioimpedance.assessments.care-guidance.approve', $assessment->id), [
            'care_guidance' => $response->json('assistant.suggestion'), 'review_action' => 'approved',
            'assistant_output_id' => $response->json('assistant.output_id'),
        ])->assertUnprocessable();
        $this->assertNull($assessment->fresh()->care_guidance);
    }

    public function test_provider_failure_uses_a_visible_validated_local_fallback(): void
    {
        $assessment = $this->approvedAssessment();
        config(['bioimpedance.assistant.provider' => 'hybrid', 'services.openai.api_key' => 'fake-key']);
        Http::fake(['*' => Http::response(['error' => ['code' => 'credit_balance_exhausted']], 429)]);
        $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))
            ->assertOk()->assertJsonPath('assistant.status', 'generated')
            ->assertJsonPath('assistant.provider', 'local_reference_engine')
            ->assertJsonPath('assistant.fallback_used', true);
    }

    public function test_unsafe_external_claim_is_replaced_and_still_requires_approval(): void
    {
        $assessment = $this->approvedAssessment();
        config(['bioimpedance.assistant.provider' => 'hybrid', 'services.openai.api_key' => 'fake-key']);
        Http::fake(['*' => Http::response(['output' => [['content' => [[
            'type' => 'output_text', 'text' => json_encode(['claims' => [[
                'id' => 'unsafe', 'category' => 'nutrition_education', 'text' => 'Prescrevo uma dieta individualizada para esta avaliação.',
                'confidence' => 'high', 'requires_professional_review' => false,
                'source_ids' => ['brazil-food-guide-natural-foods'], 'finding_ids' => [], 'comparison_scope' => 'none',
            ]]]),
        ]]]]], 200)]);
        $response = $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))
            ->assertOk()->assertJsonPath('assistant.status', 'generated')
            ->assertJsonPath('assistant.fallback_used', true)
            ->assertJsonPath('assistant.provider', 'local_reference_engine');
        $output = BioimpedanceAiAnalysisOutput::query()->with('request')->findOrFail($response->json('assistant.output_id'));
        $this->assertNotEmpty(data_get($output->raw_response, 'generation_audit.external_validation_errors'));
        $this->assertSame([], data_get($output->raw_response, 'generation_audit.final_validation_errors'));
        $this->assertSame('unsafe', data_get($output->raw_response, 'generation_audit.external_response.claims.0.id'));
        $this->assertTrue($output->request->fallback_used);
        $this->assertSame('local_reference_engine', $output->request->provider);
        $this->assertNull($assessment->fresh()->care_guidance_approved_at);
    }

    public function test_valid_external_guidance_keeps_its_origin_and_is_not_automatically_approved(): void
    {
        $assessment = $this->approvedAssessment();
        config(['bioimpedance.assistant.provider' => 'hybrid', 'services.openai.api_key' => 'fake-key']);
        Http::preventStrayRequests();
        $claims = [
            ['id' => 'context', 'category' => 'assessment_context', 'text' => 'Os registros atuais devem ser contextualizados junto ao histórico e à avaliação profissional.', 'source_ids' => ['assessment:current']],
            ['id' => 'food', 'category' => 'nutrition_education', 'text' => 'Use a síntese aprovada para conversar sobre alimentação variada, incluindo frutas, verduras e feijões, sem definir uma dieta pela balança.', 'source_ids' => ['observation:approved', 'brazil-food-guide-natural-foods']],
            ['id' => 'movement', 'category' => 'movement_education', 'text' => 'Discuta possibilidades de caminhadas e fortalecimento com o profissional, após avaliar a capacidade e as condições individuais.', 'source_ids' => ['observation:approved', 'who-adult-physical-activity']],
        ];
        $payload = ['claims' => array_map(fn ($claim) => [...$claim, 'confidence' => 'moderate', 'requires_professional_review' => true, 'finding_ids' => [], 'comparison_scope' => 'none'], $claims)];
        Http::fake(['*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode($payload)]]]]])]);
        $response = $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))
            ->assertOk()->assertJsonPath('assistant.status', 'generated')
            ->assertJsonPath('assistant.provider', 'openai')->assertJsonPath('assistant.fallback_used', false);
        $output = BioimpedanceAiAnalysisOutput::query()->findOrFail($response->json('assistant.output_id'));
        $this->assertSame('openai', $output->raw_response['provider']);
        $this->assertSame([], data_get($output->raw_response, 'generation_audit.external_validation_errors'));
        $this->assertNull($assessment->fresh()->care_guidance_approved_at);
    }

    public function test_edited_observation_does_not_implicitly_approve_original_findings(): void
    {
        $assessment = $this->approvedAssessment();
        $output = BioimpedanceAiAnalysisOutput::query()->whereNotNull('approved_at')->firstOrFail();
        $this->patchJson(route('bioimpedance.assessments.observation.approve', $assessment->id), [
            'notes' => 'Observação revisada pelo profissional com interpretação própria dos registros.',
            'review_action' => 'edited', 'assistant_output_id' => $output->id,
        ])->assertOk();
        $response = $this->getJson(route('bioimpedance.assessments.care-guidance.suggestion', $assessment->id))->assertOk();
        $care = BioimpedanceAiAnalysisOutput::query()->findOrFail($response->json('assistant.output_id'));
        $this->assertSame([], data_get($care->request->structured_context, 'approved_observation.findings'));
        $this->assertSame($assessment->fresh()->notes, data_get($care->request->structured_context, 'professional_observation'));
    }

    private function approvedAssessment(): BioimpedanceAssessment
    {
        config(['bioimpedance.assistant.provider' => 'local_reference_engine', 'services.openai.api_key' => null]);
        $user = User::factory()->create(['role' => User::ROLE_PROFESSIONAL]);
        $client = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Cliente de teste', 'birth_date' => '1981-07-03', 'biological_sex' => 'male', 'height_cm' => 174,
        ])->assertCreated();
        $created = $this->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $client->json('client.id'), 'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 80, 'body_fat_percentage' => 24, 'skeletal_muscle_percentage' => 35,
            'resting_metabolism_kcal' => 1700, 'visceral_fat_level' => 9,
        ])->assertCreated();
        $id = $created->json('assessment.id');
        $suggestion = $this->getJson(route('bioimpedance.assessments.observation-suggestion', $id))
            ->assertOk()->assertJsonPath('assistant.status', 'generated');
        $this->patchJson(route('bioimpedance.assessments.observation.approve', $id), [
            'notes' => $suggestion->json('assistant.suggestion'), 'review_action' => 'approved',
            'assistant_output_id' => $suggestion->json('assistant.output_id'),
        ])->assertOk();

        return BioimpedanceAssessment::query()->findOrFail($id);
    }
}
