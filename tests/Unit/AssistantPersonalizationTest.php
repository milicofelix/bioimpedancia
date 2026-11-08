<?php

namespace Tests\Unit;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceKnowledgeChunk;
use App\Services\Bioimpedance\AssessmentFindingPrioritizer;
use App\Services\Bioimpedance\AssessmentNarrative;
use App\Services\Bioimpedance\BioimpedanceObservationAssistant;
use App\Services\Bioimpedance\BioimpedanceCareGuidanceAssistant;
use App\Services\Bioimpedance\CareGuidanceSemanticValidator;
use App\Services\Bioimpedance\ClinicalTextGuard;
use App\Services\Bioimpedance\ContextualCareEducation;
use Tests\Fixtures\BodyCompositionProfiles;
use Tests\TestCase;

class AssistantPersonalizationTest extends TestCase
{
    public function test_all_regions_are_retained_and_trunk_is_prioritized_for_both_profiles(): void
    {
        foreach ([true, false] as $profile) {
            $context = BodyCompositionProfiles::context($profile);
            $attention = (new AssessmentFindingPrioritizer)->attention($context['findings']);
            $fat = collect($attention)->where('metric', 'fat')->values();
            $this->assertCount(5, $fat);
            $this->assertSame('trunk', $fat[0]['region']);
            $points = (new AssessmentNarrative)->segmentPoints($context);
            $this->assertCount(10, array_unique(collect($points)->pluck('source_chunk_ids')->flatten()->all()));
            $this->assertStringContainsString('Maior percentual relativo à referência desse grupo: tronco', $points[0]['text']);
            $this->assertStringContainsString($profile ? '335,7%' : '316,5%', $points[0]['text']);
        }
    }

    public function test_unverified_classifications_are_not_promoted_to_verified_attention(): void
    {
        $context = BodyCompositionProfiles::context(false);
        $context['findings'][0]['priority'] = 'context';
        $context['findings'][0]['classification_origin'] = 'recorded_unverified';
        $ordered = (new AssessmentFindingPrioritizer)->ordered(array_reverse($context['findings']));
        $this->assertSame('review', $ordered[0]['priority']);
        $this->assertNotSame('recorded_unverified', $ordered[0]['classification_origin']);
    }

    public function test_local_observation_is_interpretive_and_baseline_is_not_repeated(): void
    {
        foreach ([true, false] as $profile) {
            $context = BodyCompositionProfiles::context($profile);
            $context['metrics'] = ['bmi' => ['classification' => $profile ? 'Obesidade grau I' : 'Sobrepeso']];
            $assessment = new BioimpedanceAssessment(['weight_kg' => $profile ? 92.1 : 75.1, 'device_model' => 'Bodyscan Pro']);
            $chunks = collect(['who-adult-bmi-classification', 'vita-scan-measurement-protocol', 'vita-scan-assistant-safety-policy'])
                ->map(fn ($key) => new BioimpedanceKnowledgeChunk(['chunk_key' => $key]));
            $assistant = app(BioimpedanceObservationAssistant::class);
            $response = (new \ReflectionMethod($assistant, 'generateStructuredResponse'))->invoke($assistant, $assessment, $context, $chunks);
            $text = $response['professional_observation'];
            $this->assertSame('generated', $response['status']);
            $this->assertSame(1, substr_count($text, 'linha de base'));
            $this->assertStringContainsString('tronco', $text);
            $this->assertStringContainsString('aspectos diferentes', $text);
            $this->assertStringNotContainsString('WHR:', $text);
            $this->assertLessThanOrEqual(2000, mb_strlen($text));
            $segmentAttentionIds = collect($response['attention_points'])->pluck('source_chunk_ids')->flatten()
                ->filter(fn ($id) => str_starts_with($id, 'assessment:segmental:'))->all();
            $reviewIds = collect($context['findings'])->where('priority', 'review')->pluck('id')->all();
            $this->assertSame([], array_diff($segmentAttentionIds, $reviewIds));
        }
    }

    public function test_nutrition_and_movement_change_with_the_profile_and_remain_educational(): void
    {
        $composer = new ContextualCareEducation;
        $male = $composer->claims(BodyCompositionProfiles::context(true));
        $female = $composer->claims(BodyCompositionProfiles::context(false));
        $this->assertNotSame($male[0]['text'], $female[0]['text']);
        $this->assertNotSame($male[1]['text'], $female[1]['text']);
        $this->assertStringContainsString('não define uma meta alimentar', $male[0]['text']);
        $this->assertStringContainsString('sozinha, não resume sua composição', $female[0]['text']);
        $this->assertStringContainsString('não justificam, sozinhas, treinar mais um membro', $male[1]['text']);
        $this->assertStringContainsString('Todas as regiões registradas estão em Padrão', $female[1]['text']);
        foreach ([$male, $female] as $claims) {
            $this->assertStringContainsString('tronco', $claims[0]['text']);
            $this->assertStringContainsString('arroz e feijão', $claims[0]['text']);
            $this->assertStringContainsString('caminhadas', $claims[1]['text']);
            $this->assertStringContainsString('eventual liberação profissional', $claims[1]['text']);
            $this->assertContains('observation:approved', $claims[2]['source_ids']);
            foreach ($claims as $claim) {
                $this->assertTrue($claim['requires_professional_review']);
                $this->assertLessThanOrEqual(900, mb_strlen($claim['text']));
            }
        }
    }

    public function test_educational_claims_pass_validation_with_real_evidence_ids(): void
    {
        $chunks = $this->chunks();
        foreach ([true, false] as $profile) {
            $context = BodyCompositionProfiles::context($profile);
            $claims = (new ContextualCareEducation)->claims($context);
            foreach ($claims as &$claim) {
                $claim['finding_ids'] = array_values(array_intersect($claim['source_ids'], array_column($context['findings'], 'id')));
                $claim['comparison_scope'] = 'none';
            }
            unset($claim);
            $result = (new CareGuidanceSemanticValidator)->validate(['claims' => $claims], $chunks, $context);
            $this->assertSame([], $result['errors'], json_encode($result['blocked_claims'], JSON_UNESCAPED_UNICODE));
        }
    }

    public function test_guard_accepts_disclaimers_and_retome_but_rejects_diagnosis_and_prescription(): void
    {
        $guard = new ClinicalTextGuard;
        $this->assertFalse($guard->contains('Retome o acompanhamento com o profissional.', 'tome '));
        $this->assertTrue($guard->contains('Tome este medicamento.', 'tome '));
        $this->assertFalse($guard->contains('O índice não representa diagnóstico de uma condição clínica.', 'diagnóstico de'));
        $this->assertFalse($guard->contains('Os índices não representam diagnóstico de uma condição clínica.', 'diagnóstico de'));
        $this->assertTrue($guard->contains('O índice confirma diagnóstico de uma condição clínica.', 'diagnóstico de'));
        $this->assertTrue($guard->contains('Não representa diagnóstico de uma condição; prescrevo uma dieta.', 'prescrevo'));
    }

    public function test_observation_validator_accepts_caution_but_still_blocks_diagnosis_and_unknown_sources(): void
    {
        $assistant = app(BioimpedanceObservationAssistant::class);
        $validate = new \ReflectionMethod($assistant, 'validateResponse');
        $context = BodyCompositionProfiles::context(false);
        $assessment = new BioimpedanceAssessment(['device_model' => 'Bodyscan Pro', 'analysis' => []]);
        $response = [
            'status' => 'generated', 'summary' => 'Descrição para revisão profissional.',
            'professional_observation' => 'O índice não representa diagnóstico de uma condição clínica. Retome o acompanhamento com o profissional para contextualizar os registros.',
            'positive_points' => [], 'attention_points' => [], 'general_guidance' => [], 'prohibited_content_detected' => false,
        ];
        $this->assertSame([], $validate->invoke($assistant, $response, $assessment, collect(), $context));
        $unsafe = [...$response, 'professional_observation' => 'O índice confirma diagnóstico de uma condição clínica. Prescrevo uma dieta individualizada.'];
        $this->assertNotEmpty($validate->invoke($assistant, $unsafe, $assessment, collect(), $context));
        $unknown = [...$response, 'attention_points' => [['text' => 'Um ponto para revisão profissional.', 'source_chunk_ids' => ['invented-source']]]];
        $this->assertContains('Fonte inexistente ou não recuperada: invented-source', $validate->invoke($assistant, $unknown, $assessment, collect(), $context));
    }

    public function test_complete_local_care_response_passes_without_cutting_attention_findings(): void
    {
        foreach ([true, false] as $profile) {
            $context = BodyCompositionProfiles::context($profile);
            $prioritized = (new AssessmentFindingPrioritizer)->attention($context['findings']);
            $narrative = new AssessmentNarrative;
            $context['prioritized_findings'] = $prioritized;
            $context['attention_points'] = [
                ...collect($prioritized)->where('kind', '!=', 'segmental')->pluck('text')->all(),
                $narrative->classificationOverview($prioritized, 'fat'),
                $narrative->classificationOverview($prioritized, 'muscle'),
            ];
            $context['attention_points'] = array_values(array_filter($context['attention_points']));
            $assistant = app(BioimpedanceCareGuidanceAssistant::class);
            $payload = (new \ReflectionMethod($assistant, 'localResponse'))->invoke($assistant, $context);
            $result = (new CareGuidanceSemanticValidator)->validate($payload, $this->chunks(), $context);
            $this->assertSame([], $result['errors'], json_encode($result['blocked_claims'], JSON_UNESCAPED_UNICODE));
            $sources = collect($result['accepted_claims'])->pluck('source_ids')->flatten()->all();
            foreach ($prioritized as $finding) {
                $this->assertContains($finding['id'], $sources);
            }
        }
    }

    public function test_editorial_metadata_preserves_the_existing_approval_fingerprint(): void
    {
        $context = BodyCompositionProfiles::context(false);
        $service = new \App\Services\Bioimpedance\AssessmentFindings;
        $legacy = $context;
        $legacy['segmental_analysis']['current'] = array_map(fn ($segment) => \Illuminate\Support\Arr::except($segment, ['reference_min_percentage', 'reference_max_percentage']), $context['segmental_analysis']['current']);
        $this->assertSame($service->fingerprint($legacy), $service->fingerprint($context));
        $changed = $context;
        $changed['segmental_analysis']['current'][0]['device_classification'] = 'low';
        $this->assertNotSame($service->fingerprint($context), $service->fingerprint($changed));
    }

    public function test_absent_classification_is_reported_without_inventing_a_label(): void
    {
        $context = BodyCompositionProfiles::context(false);
        $context['segmental_analysis']['current'][4]['device_classification'] = null;
        $context['findings'] = (new \App\Services\Bioimpedance\AssessmentFindings)->from($context);
        $unknown = collect($context['findings'])->firstWhere('id', 'assessment:segmental:current:fat:trunk:center');
        $this->assertNull($unknown['classification']);
        $this->assertSame('context', $unknown['priority']);
        $text = collect((new AssessmentNarrative)->segmentPoints($context))->pluck('text')->implode(' ');
        $this->assertStringContainsString('Não identificada', $text);
        $this->assertStringContainsString('tronco', $text);
    }

    public function test_mixed_classifications_and_origins_remain_compact_without_reclassification(): void
    {
        $context = BodyCompositionProfiles::context(false);
        $origins = ['equipment_report', 'equipment_reference_range', 'professional_review', 'recorded_unverified'];
        foreach ($context['segmental_analysis']['current'] as $index => &$segment) {
            $segment['device_classification'] = ['low', 'standard', 'high'][$index % 3];
            $segment['classification_origin'] = $origins[$index % 4];
        }
        unset($segment);
        $context['findings'] = (new \App\Services\Bioimpedance\AssessmentFindings)->from($context);
        $points = (new AssessmentNarrative)->segmentPoints($context);
        $this->assertCount(10, array_unique(collect($points)->pluck('source_chunk_ids')->flatten()->all()));
        $compact = (new AssessmentNarrative)->compactSegments($context);
        foreach (['braço esquerdo', 'braço direito', 'tronco', 'perna esquerda', 'perna direita', 'Alto', 'Padrão', 'Baixo'] as $label) {
            $this->assertStringContainsString($label, $compact);
        }
        $this->assertLessThan(850, mb_strlen($compact));
        $claims = (new ContextualCareEducation)->claims($context);
        $this->assertLessThanOrEqual(900, mb_strlen($claims[1]['text']));
    }

    private function chunks()
    {
        return collect([
            ['brazil-food-guide-natural-foods', ['food']], ['brazil-food-guide-ultraprocessed', ['food']],
            ['who-adult-physical-activity', ['physical_activity']], ['vita-scan-care-guidance-safety', ['safety']],
        ])->map(fn ($chunk) => new BioimpedanceKnowledgeChunk(['chunk_key' => $chunk[0], 'tags' => $chunk[1]]));
    }
}
