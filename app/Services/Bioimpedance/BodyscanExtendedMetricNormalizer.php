<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanExtendedMetricCatalog;
use App\Domain\Bioimpedance\BodyscanMetricIntegrity;
use Illuminate\Support\Str;

final class BodyscanExtendedMetricNormalizer
{
    /** @return list<array<string, mixed>> */
    public function normalize(array $items, string $application, string $layout, array &$readingIssues = []): array
    {
        $availableCodes = BodyscanExtendedMetricCatalog::availableForLayout($layout);
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = $this->text($item['code'] ?? null, 80);
            if ($code === null || ! in_array($code, $availableCodes, true) || isset($normalized[$code])) {
                continue;
            }

            $label = $this->text($item['original_label'] ?? null, 160);
            $rawValue = $this->text($item['raw_value'] ?? null, 2000);
            $numericValue = $this->number($item['numeric_value'] ?? null);
            $textValue = $this->text($item['text_value'] ?? null, 2000);
            $isTextual = BodyscanExtendedMetricCatalog::isTextual($code);

            if ($label === null || ! BodyscanExtendedMetricCatalog::recognizesLabel($code, $label) || $rawValue === null
                || ($isTextual && $textValue === null)
                || (! $isTextual && $numericValue === null)) {
                continue;
            }

            $minimum = $this->number($item['printed_minimum'] ?? null);
            $maximum = $this->number($item['printed_maximum'] ?? null);
            if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
                continue;
            }

            $normalized[$code] = [
                'metric_code' => $code,
                'original_label' => $label,
                'numeric_value' => $isTextual ? null : $numericValue,
                'text_value' => $isTextual ? $textValue : null,
                'unit' => $this->text($item['unit'] ?? null, 30),
                'application_classification' => $this->text($item['classification'] ?? null, 120),
                'printed_minimum' => $minimum,
                'printed_maximum' => $maximum,
                'weight_proportion_percentage' => $this->number($item['weight_proportion_percentage'] ?? null),
                'source_application' => $application,
                'source_layout' => $layout,
                'raw_extracted_value' => $rawValue,
                'reference_version' => BodyscanExtendedMetricCatalog::REFERENCE_VERSION,
            ];
            if ($issue = BodyscanMetricIntegrity::readingIssue($normalized[$code])) {
                $readingIssues[] = ['field' => 'extended_metrics.'.$code, 'value' => $rawValue, 'reason' => $issue];
                unset($normalized[$code]);
            }
        }

        $this->removeAmbiguousBodyFatRange($normalized);

        return array_values($normalized);
    }

    /**
     * The technical Fitdays table prints the fat-mass range beside the value in kg
     * and the body-fat percentage in a separate proportion column. OCR can attach
     * that kg range to both metrics. Keep the original values and classifications,
     * but do not persist a duplicated range as if it were a percentage reference.
     *
     * @param  array<string, array<string, mixed>>  $metrics
     */
    private function removeAmbiguousBodyFatRange(array &$metrics): void
    {
        $percentage = $metrics['body_fat_percentage'] ?? null;
        $mass = $metrics['fat_mass'] ?? null;

        if (! is_array($percentage) || ! is_array($mass)) {
            return;
        }

        $percentageMinimum = $percentage['printed_minimum'] ?? null;
        $percentageMaximum = $percentage['printed_maximum'] ?? null;
        $massMinimum = $mass['printed_minimum'] ?? null;
        $massMaximum = $mass['printed_maximum'] ?? null;

        if ($percentageMinimum === null || $percentageMaximum === null
            || $massMinimum === null || $massMaximum === null) {
            return;
        }

        if (abs((float) $percentageMinimum - (float) $massMinimum) > 0.001
            || abs((float) $percentageMaximum - (float) $massMaximum) > 0.001) {
            return;
        }

        $metrics['body_fat_percentage']['printed_minimum'] = null;
        $metrics['body_fat_percentage']['printed_maximum'] = null;
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', preg_replace('/[^\d,\.\-+]/', '', trim($value)) ?? '');

        return is_numeric($normalized) && is_finite((float) $normalized) ? (float) $normalized : null;
    }

    private function text(mixed $value, int $maximum): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $maximum, '');
    }
}
