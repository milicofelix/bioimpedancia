<?php

namespace Tests\Unit;

use App\Services\Bioimpedance\AssessmentNarrative;
use Tests\TestCase;

class AssessmentNarrativeTest extends TestCase
{
    public function test_summary_preserves_classification_and_highlights_trunk_in_natural_language(): void
    {
        $regions = ['braço esquerdo', 'braço direito', 'tronco', 'perna esquerda', 'perna direita'];
        $findings = array_map(fn (string $region, int $index) => [
            'id' => 'segment-'.$index,
            'kind' => 'segmental', 'metric' => 'fat', 'classification' => 'high',
            'classification_origin' => 'equipment_report', 'region_label' => $region,
            'reference_percentage' => $region === 'tronco' ? 327.6 : 231.4,
        ], $regions, array_keys($regions));

        $points = (new AssessmentNarrative)->segmentPoints(['findings' => $findings]);
        $this->assertCount(1, $points);
        $this->assertStringContainsString('nas cinco regiões avaliadas', $points[0]['text']);
        $this->assertStringContainsString('classificação Alto no relatório de origem', $points[0]['text']);
        $this->assertStringContainsString('tronco (327,6%)', $points[0]['text']);
        $this->assertCount(5, $points[0]['source_chunk_ids']);
    }
}
