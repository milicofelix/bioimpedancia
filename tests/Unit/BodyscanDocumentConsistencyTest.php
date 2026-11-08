<?php

namespace Tests\Unit;

use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement as Segment;
use App\Services\Bioimpedance\BodyscanDocumentConsistency;
use PHPUnit\Framework\TestCase;

class BodyscanDocumentConsistencyTest extends TestCase
{
    public function test_printed_difference_is_reported_without_changing_measurements(): void
    {
        $segments = $this->segments();
        $original = $segments;
        $warnings = (new BodyscanDocumentConsistency)->warnings(['fat_mass_kg' => 25.7], $segments);

        $this->assertCount(1, $warnings);
        $this->assertSame('segmental_fat_total_difference', $warnings[0]['code']);
        $this->assertSame(24.9, $warnings[0]['segmental_sum_kg']);
        $this->assertSame(0.8, $warnings[0]['difference_kg']);
        $this->assertSame($original, $segments);
    }

    public function test_missing_and_duplicate_regions_are_not_treated_as_zero(): void
    {
        $service = new BodyscanDocumentConsistency;
        $segments = $this->segments();
        $this->assertSame([], $service->warnings(['fat_mass_kg' => 25.7], array_slice($segments, 1)));
        $this->assertSame([], $service->warnings(['fat_mass_kg' => 25.7], [...$segments, $segments[0]]));
        $segments[2]['mass_kg'] = null;
        $this->assertSame([], $service->warnings(['fat_mass_kg' => 25.7], $segments));
    }

    public function test_tolerance_and_missing_total_do_not_create_warnings(): void
    {
        $service = new BodyscanDocumentConsistency;
        $this->assertSame([], $service->warnings(['fat_mass_kg' => 25.4], $this->segments()));
        $this->assertSame([], $service->warnings([], $this->segments()));
    }

    private function segments(): array
    {
        return array_map(fn (array $region, float $mass) => [
            ...$region, 'type' => Segment::TYPE_FAT, 'mass_kg' => $mass,
        ], Segment::EXPECTED_SEGMENTS, [1.6, 1.6, 13.9, 3.9, 3.9]);
    }
}
