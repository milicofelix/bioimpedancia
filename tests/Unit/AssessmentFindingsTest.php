<?php

namespace Tests\Unit;

use App\Services\Bioimpedance\AssessmentFindings;
use App\Services\Bioimpedance\ContextualCareEducation;
use App\Services\Bioimpedance\CareGuidanceSemanticValidator;
use Tests\TestCase;

class AssessmentFindingsTest extends TestCase
{
    public function test_missing_values_are_not_replaced_with_measurements(): void
    {
        $this->assertSame([], (new AssessmentFindings)->from(['general_metrics' => [
            ['code' => 'body_water_percentage', 'value' => null],
        ]]));
    }

    public function test_printed_and_derived_classifications_keep_their_origins(): void
    {
        $service = new AssessmentFindings;
        $segment = ['evidence_id' => 'assessment:segmental:current:fat:trunk:center', 'type' => 'fat', 'region' => 'trunk', 'side' => 'center', 'device_classification' => 'high', 'reference_percentage' => 250.0];
        foreach (['equipment_report', 'equipment_reference_range', 'recorded_unverified'] as $origin) {
            $finding = $service->from(['segmental_analysis' => ['current' => [[...$segment, 'classification_origin' => $origin]]]])[0];
            $this->assertSame($origin, $finding['classification_origin']);
            $this->assertSame($origin === 'recorded_unverified' ? 'context' : 'review', $finding['priority']);
            $this->assertSame([$segment['evidence_id']], $finding['source_ids']);
        }
    }

    public function test_education_changes_with_findings_and_does_not_infer_habits(): void
    {
        $composer = new ContextualCareEducation;
        $baseline = $composer->claims([]);
        $specific = $composer->claims(['findings' => [[
            'id' => 'assessment:segmental:current:fat:trunk:center',
            'kind' => 'segmental', 'metric' => 'fat', 'priority' => 'review',
            'text' => 'Gordura segmentar no tronco: Alto no relatório de origem (250,0% da referência).',
        ]]]);
        $this->assertNotSame($baseline[0]['text'], $specific[0]['text']);
        $this->assertStringContainsString('Não há informação sobre sua alimentação', $baseline[0]['text']);
        $this->assertStringContainsString('não informa seu nível de atividade', $baseline[1]['text']);
        $this->assertContains('assessment:segmental:current:fat:trunk:center', $specific[0]['source_ids']);
        $this->assertTrue($specific[0]['requires_professional_review']);
    }

    public function test_semantic_validation_rejects_incompatible_history_and_unknown_findings(): void
    {
        $validation = (new CareGuidanceSemanticValidator)->validate(['claims' => [[
            'id' => 'history', 'category' => 'assessment_context',
            'text' => 'Os registros apresentam diferenças que precisam ser revistas pelo profissional.',
            'confidence' => 'moderate', 'requires_professional_review' => false,
            'source_ids' => ['assessment:history-profile'], 'finding_ids' => ['invented-finding'],
            'comparison_scope' => 'numeric_change',
        ]]], collect(), ['evidence_ids' => ['assessment:history-profile'], 'history_comparison' => ['automatic_interpretation_allowed' => false]]);
        $reasons = $validation['blocked_claims'][0]['blocked_reasons'];
        $this->assertContains('A comparação proposta não é permitida pelo perfil do histórico.', $reasons);
        $this->assertContains('O achado citado não está disponível ou não possui evidência na afirmação.', $reasons);
        $this->assertContains('A revisão profissional obrigatória não foi sinalizada.', $reasons);
    }

    public function test_measurement_changes_invalidate_the_fingerprint(): void
    {
        $service = new AssessmentFindings;
        $first = ['measurement_profile' => ['athlete_mode' => false], 'general_metrics' => [['value' => 80.0]]];
        $this->assertNotSame($service->fingerprint($first), $service->fingerprint([...$first, 'measurement_profile' => ['athlete_mode' => true]]));
        $this->assertSame($service->fingerprint($first), $service->fingerprint([...$first, 'professional_observation' => 'Texto revisado.']));
    }
}
