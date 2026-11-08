<?php

namespace App\Domain\Bioimpedance;

final class BodyscanMetricIntegrity
{
    public static function readingIssue(array $metric): ?string
    {
        if (($metric['metric_code'] ?? null) !== 'subcutaneous_fat') {
            return null;
        }

        $value = $metric['numeric_value'] ?? null;
        $proportion = $metric['weight_proportion_percentage'] ?? null;
        if (trim((string) ($metric['unit'] ?? '')) !== '%'
            || ! is_numeric($value) || (float) $value < 0 || (float) $value > 100
            || (is_numeric($proportion) && ((float) $proportion < 0 || (float) $proportion > 100))) {
            return 'Não foi possível associar com segurança a gordura subcutânea ao seu percentual. Confira o rótulo, a unidade e o valor na imagem; não use a massa ou o percentual de referência da gordura do tronco neste campo.';
        }

        return null;
    }
}
