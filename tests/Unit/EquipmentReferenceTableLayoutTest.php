<?php

namespace Tests\Unit;

use Tests\TestCase;

class EquipmentReferenceTableLayoutTest extends TestCase
{
    public function test_reference_rows_use_bounded_tables_with_explicit_headers(): void
    {
        $metrics = array_map(fn (int $index) => [
            'metric_code' => 'asmi',
            'original_label' => 'Indicador '.$index,
            'numeric_value' => $index,
            'unit' => 'kg/m²',
        ], range(1, 6));

        $html = $this->renderReferences($metrics);

        $this->assertSame(2, substr_count($html, 'class="equipment-reference-block"'));
        $this->assertSame(2, substr_count($html, 'class="equipment-reference-table"'));
        $this->assertSame(8, substr_count($html, 'scope="col"'));
        $this->assertStringNotContainsString('<caption', $html);
        $this->assertStringNotContainsString('class="segmental-comparison"', $html);
        $this->assertStringContainsString('Referências - continuação', $html);
        foreach (range(1, 6) as $index) {
            $this->assertSame(1, substr_count($html, '<strong>Indicador '.$index.'</strong>'));
        }
    }

    public function test_empty_reference_collection_does_not_leave_a_heading_or_empty_table(): void
    {
        $html = $this->renderReferences([]);

        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('class="section-title"', $html);
    }

    private function renderReferences(array $metrics): string
    {
        return view('bioimpedance.partials.report-equipment-references', [
            'referenceTableMetrics' => collect($metrics),
            'referenceTableTitle' => 'Referências',
            'secondaryColor' => '#052f59',
            'hasAmbiguousBodyFatRange' => false,
            'bodyBalanceItems' => [],
            'br' => fn ($value, $decimals = 1) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.'),
            'extendedMetricDisplayValue' => fn (array $metric) => (string) $metric['numeric_value'].' '.$metric['unit'],
        ])->render();
    }
}
