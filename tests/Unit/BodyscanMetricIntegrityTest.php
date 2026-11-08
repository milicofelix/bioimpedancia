<?php

namespace Tests\Unit;

use App\Domain\Bioimpedance\BodyscanMetricIntegrity;
use App\Domain\Bioimpedance\BodyscanReportLayout;
use App\Services\Bioimpedance\BodyscanExtendedMetricNormalizer;
use Tests\TestCase;

class BodyscanMetricIntegrityTest extends TestCase
{
    public function test_trunk_mass_and_reference_cannot_be_published_as_subcutaneous_fat(): void
    {
        $metric = ['metric_code' => 'subcutaneous_fat', 'numeric_value' => 13.9, 'unit' => 'kg', 'weight_proportion_percentage' => 327.6];
        $original = $metric;
        $this->assertNotNull(BodyscanMetricIntegrity::readingIssue($metric));
        $this->assertSame($original, $metric);
        $this->assertNotNull(BodyscanMetricIntegrity::readingIssue([...$metric, 'unit' => '%']));
    }

    public function test_printed_subcutaneous_percentage_and_proprietary_index_are_preserved(): void
    {
        $this->assertNull(BodyscanMetricIntegrity::readingIssue([
            'metric_code' => 'subcutaneous_fat', 'numeric_value' => 19.4, 'unit' => '%',
        ]));
        $this->assertNull(BodyscanMetricIntegrity::readingIssue([
            'metric_code' => 'proprietary_obesity_index', 'numeric_value' => 141, 'unit' => '%',
        ]));
    }

    public function test_normalizer_reports_ambiguous_reading_without_overwriting_the_source(): void
    {
        $items = [[
            'code' => 'subcutaneous_fat', 'original_label' => 'Gordura subcutânea',
            'raw_value' => 'Gordura subcutânea 13.9 kg 327.6%',
            'numeric_value' => 13.9, 'unit' => 'kg', 'weight_proportion_percentage' => 327.6,
        ]];
        $original = $items;
        $issues = [];
        $result = (new BodyscanExtendedMetricNormalizer)->normalize($items, 'fitdays', BodyscanReportLayout::FITDAYS_TECHNICAL, $issues);
        $this->assertSame([], $result);
        $this->assertCount(1, $issues);
        $this->assertSame('extended_metrics.subcutaneous_fat', $issues[0]['field']);
        $this->assertSame($original, $items);
    }
}
