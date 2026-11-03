<?php

namespace Tests\Unit;

use App\Domain\Bioimpedance\BodyscanOcrProfileRegistry;
use App\Domain\Bioimpedance\BodyscanReportLayout;
use App\Services\Bioimpedance\BodyscanGeneralMetricRecovery;
use Tests\TestCase;

class BodyscanGeneralMetricRecoveryTest extends TestCase
{
    public function test_explicit_percentages_can_fill_missing_general_fields(): void
    {
        $profile = (new BodyscanOcrProfileRegistry)->forLayout(BodyscanReportLayout::FITDAYS_VERTICAL);
        $result = (new BodyscanGeneralMetricRecovery)->recover([], [
            $this->metric('muscle_rate', 'Taxa muscular', 68.0, '%', 'Taxa muscular 68,0% Excelente'),
            $this->metric('subcutaneous_fat', 'Gordura subcutânea', 19.4, '%', 'Gordura subcutânea 19,4% Alto'),
        ], $profile);
        $this->assertSame(68.0, $result['muscle_rate_percentage']['value']);
        $this->assertSame(19.4, $result['subcutaneous_fat_percentage']['value']);
        $this->assertSame('extended_metrics.muscle_rate.numeric_value', $result['muscle_rate_percentage']['source']);
    }

    public function test_technical_report_uses_printed_muscle_proportion_not_mass(): void
    {
        $profile = (new BodyscanOcrProfileRegistry)->forLayout(BodyscanReportLayout::FITDAYS_TECHNICAL);
        $row = [...$this->metric('muscle_mass', 'Massa muscular', 64.2, 'kg', 'Massa muscular 64.2 (45.4–56.5) 68.0 Excelente'), 'weight_proportion_percentage' => 68.0];
        $result = (new BodyscanGeneralMetricRecovery)->recover([], [$row], $profile);
        $this->assertSame(68.0, $result['muscle_rate_percentage']['value']);
        $this->assertSame('extended_metrics.muscle_mass.weight_proportion_percentage', $result['muscle_rate_percentage']['source']);
    }

    public function test_mass_alone_and_skeletal_muscle_cannot_fill_muscle_rate(): void
    {
        $profile = (new BodyscanOcrProfileRegistry)->forLayout(BodyscanReportLayout::FITDAYS_TECHNICAL);
        $this->assertSame([], (new BodyscanGeneralMetricRecovery)->recover([], [
            $this->metric('muscle_mass', 'Massa muscular', 64.2, 'kg', 'Massa muscular 64.2 kg'),
            $this->metric('skeletal_muscle', 'Músculo esquelético', 41.8, '%', 'Músculo esquelético 41.8%'),
        ], $profile));
    }

    public function test_conflicting_readings_remain_pending(): void
    {
        $profile = (new BodyscanOcrProfileRegistry)->forLayout(BodyscanReportLayout::FITDAYS_TECHNICAL);
        $this->assertSame([], (new BodyscanGeneralMetricRecovery)->recover([], [
            $this->metric('muscle_rate', 'Taxa muscular', 67.0, '%', 'Taxa muscular 67.0%'),
            [...$this->metric('muscle_mass', 'Massa muscular', 64.2, 'kg', 'Massa muscular 64.2 kg 68.0%'), 'weight_proportion_percentage' => 68.0],
        ], $profile));
    }

    public function test_existing_values_are_not_overwritten_and_raw_evidence_is_required(): void
    {
        $profile = (new BodyscanOcrProfileRegistry)->forLayout(BodyscanReportLayout::FITDAYS_VERTICAL);
        $service = new BodyscanGeneralMetricRecovery;
        $row = $this->metric('muscle_rate', 'Taxa muscular', 68.0, '%', 'Taxa muscular 68.0%');
        $this->assertSame([], $service->recover(['muscle_rate_percentage' => 67.0], [$row], $profile));
        $this->assertSame([], $service->recover([], [[...$row, 'raw_extracted_value' => 'Taxa muscular ilegível']], $profile));
        $this->assertSame([], $service->recover([], [[...$row, 'unit' => 'kg']], $profile));
    }

    private function metric(string $code, string $label, float $value, string $unit, string $raw): array
    {
        return ['metric_code' => $code, 'original_label' => $label, 'numeric_value' => $value, 'unit' => $unit, 'raw_extracted_value' => $raw];
    }
}
