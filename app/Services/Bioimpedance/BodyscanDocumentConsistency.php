<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement as Segment;

/** Arithmetic checks are not evidence of an OCR reading error. Never rewrite measurements. */
final class BodyscanDocumentConsistency
{
    public function warnings(array $metrics, array $segments): array
    {
        $total = $metrics['fat_mass_kg'] ?? null;
        if (! is_numeric($total) || (float) $total <= 0) {
            return [];
        }

        $sum = 0.0;
        foreach (Segment::EXPECTED_SEGMENTS as $expected) {
            $matches = array_values(array_filter($segments, fn (array $segment) =>
                ($segment['type'] ?? null) === Segment::TYPE_FAT
                && ($segment['region'] ?? null) === $expected['region']
                && ($segment['side'] ?? null) === $expected['side']));

            // Incomplete or ambiguous readings must not be counted as zero.
            if (count($matches) !== 1 || ! is_numeric($matches[0]['mass_kg'] ?? null)) {
                return [];
            }
            $sum += (float) $matches[0]['mass_kg'];
        }

        $difference = abs($sum - (float) $total);
        $tolerance = max(0.5, (float) $total * 0.03);
        if ($difference <= $tolerance) {
            return [];
        }

        return [[
            'code' => 'segmental_fat_total_difference',
            'segmental_sum_kg' => round($sum, 2),
            'total_fat_mass_kg' => (float) $total,
            'difference_kg' => round($difference, 2),
            'tolerance_kg' => $tolerance,
        ]];
    }
}
