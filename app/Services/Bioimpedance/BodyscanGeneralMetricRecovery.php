<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanOcrProfile;
use App\Domain\Bioimpedance\BodyscanReportLayout;

final class BodyscanGeneralMetricRecovery
{
    /** Copy printed percentages omitted from the general block, never calculate them. */
    public function recover(array $metrics, array $extendedMetrics, BodyscanOcrProfile $profile): array
    {
        $recovered = [];
        foreach (['muscle_rate_percentage' => 'muscle_rate', 'subcutaneous_fat_percentage' => 'subcutaneous_fat'] as $field => $code) {
            if (! $profile->supportsMetric($field) || (isset($metrics[$field]) && $metrics[$field] !== '')) {
                continue;
            }
            $candidates = [];
            foreach ($extendedMetrics as $metric) {
                $direct = ($metric['metric_code'] ?? null) === $code;
                $proportion = $field === 'muscle_rate_percentage'
                    && $profile->layout === BodyscanReportLayout::FITDAYS_TECHNICAL
                    && ($metric['metric_code'] ?? null) === 'muscle_mass';
                if (! $direct && ! $proportion) {
                    continue;
                }
                $value = $proportion ? ($metric['weight_proportion_percentage'] ?? null) : ($metric['numeric_value'] ?? null);
                $raw = $metric['raw_extracted_value'] ?? null;
                $label = $metric['original_label'] ?? null;
                $unit = $metric['unit'] ?? null;
                if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100
                    || ! is_string($raw) || ! $profile->recognizesMetricLabel($field, $label)
                    || ($direct && $unit !== null && trim($unit) !== '%')
                    || ! $this->containsPrintedNumber($raw, (float) $value, $direct && $unit === null)) {
                    continue;
                }
                $candidates[] = [
                    'value' => (float) $value,
                    'label' => $label,
                    'raw_value' => $raw,
                    'source' => 'extended_metrics.'.$metric['metric_code'].'.'.($proportion ? 'weight_proportion_percentage' : 'numeric_value'),
                ];
            }
            if ($candidates !== [] && count(array_unique(array_column($candidates, 'value'), SORT_REGULAR)) === 1) {
                $recovered[$field] = $candidates[0];
            }
        }

        return $recovered;
    }

    private function containsPrintedNumber(string $raw, float $value, bool $requirePercent): bool
    {
        preg_match_all('/(?<![\d.,])([0-9]+(?:[.,][0-9]+)?)(?![\d.,])\s*(%)?/u', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ((! $requirePercent || ($match[2] ?? null) === '%')
                && abs((float) str_replace(',', '.', $match[1]) - $value) < 0.000001) {
                return true;
            }
        }

        return false;
    }
}
