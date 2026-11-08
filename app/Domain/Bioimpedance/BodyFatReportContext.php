<?php

namespace App\Domain\Bioimpedance;

final class BodyFatReportContext
{
    public static function classification(iterable $metrics): ?array
    {
        foreach ($metrics as $metric) {
            if (data_get($metric, 'metric_code') !== 'body_fat_percentage') {
                continue;
            }
            $classification = trim((string) data_get($metric, 'application_classification', ''));
            if ($classification === '') {
                continue;
            }

            $normalized = mb_strtolower($classification);

            return [
                'classification' => $classification,
                'classification_origin' => 'equipment_report',
                'tone' => match (true) {
                    str_contains($normalized, 'baixo') || str_contains($normalized, 'fino') => 'attention',
                    str_contains($normalized, 'alto') => 'danger',
                    str_contains($normalized, 'padrão') || str_contains($normalized, 'padrao')
                        || str_contains($normalized, 'normal') || str_contains($normalized, 'excelente') => 'good',
                    default => 'neutral',
                },
            ];
        }

        return null;
    }

    public static function observationReviewNotice(?string $notes, iterable $metrics): ?string
    {
        $classification = self::classification($metrics);
        if ($classification === null || ! preg_match('/gordura corporal[^.!?\r\n]*sem classifica[çc][aã]o/iu', $notes ?? '')) {
            return null;
        }

        return 'O equipamento registra a classificação “'.$classification['classification'].'” para gordura corporal. '
            .'A síntese acima informa classificação indisponível e precisa de nova revisão profissional. O texto aprovado foi preservado.';
    }
}
