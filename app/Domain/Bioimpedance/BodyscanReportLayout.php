<?php

namespace App\Domain\Bioimpedance;

use Illuminate\Support\Str;

final class BodyscanReportLayout
{
    public const FITDAYS_TECHNICAL = 'fitdays_technical_report';

    public const FITDAYS_VERTICAL = 'fitdays_vertical_report';

    public const RELAXFIT_VERTICAL = 'relaxfit_vertical_report';

    public const LAYOUTS = [
        self::FITDAYS_TECHNICAL,
        self::FITDAYS_VERTICAL,
        self::RELAXFIT_VERTICAL,
    ];

    private const PROFILE_VERSIONS = [
        self::FITDAYS_TECHNICAL => 'fitdays-technical-1.2.0',
        self::FITDAYS_VERTICAL => 'fitdays-vertical-1.1.0',
        self::RELAXFIT_VERTICAL => 'relaxfit-vertical-1.0.0',
    ];

    /**
     * Identifies a supported layout only from visible textual evidence. The
     * order or screen coordinates of each marker are intentionally ignored.
     *
     * @param  list<string>  $evidence
     */
    public static function identify(?string $application, array $evidence): ?string
    {
        $markers = collect($evidence)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => self::normalize($value))
            ->values();

        if ($application === ScaleModel::BODYSCAN_APPLICATION_FITDAYS
            && self::containsEvery($markers, [
                'relatorio de analise de composicao corporal',
                'impedancia bioeletrica',
                'pontuacao corporal',
                'fitdays',
            ])) {
            return self::FITDAYS_TECHNICAL;
        }

        if ($application === ScaleModel::BODYSCAN_APPLICATION_FITDAYS
            && self::containsEvery($markers, [
                'limb dados',
                'controle de peso',
                'fitdays',
            ])
            && self::containsEvery($markers, ['indicador', 'valor', 'padrao'])) {
            return self::FITDAYS_VERTICAL;
        }

        if ($application === ScaleModel::BODYSCAN_APPLICATION_RELAXFIT
            && self::containsEvery($markers, [
                'relaxfit',
                'analise de gordura segmentar',
                'equilibrio muscular',
            ])
            && self::containsAny($markers, ['dados de membro', 'indice corporal'])) {
            return self::RELAXFIT_VERTICAL;
        }

        return null;
    }

    public static function profileVersion(string $layout): string
    {
        return self::PROFILE_VERSIONS[$layout] ?? throw new \InvalidArgumentException('Layout Bodyscan não reconhecido.');
    }

    public static function belongsToApplication(string $layout, string $application): bool
    {
        return match ($layout) {
            self::FITDAYS_TECHNICAL, self::FITDAYS_VERTICAL => $application === ScaleModel::BODYSCAN_APPLICATION_FITDAYS,
            self::RELAXFIT_VERTICAL => $application === ScaleModel::BODYSCAN_APPLICATION_RELAXFIT,
            default => false,
        };
    }

    private static function containsEvery($markers, array $required): bool
    {
        return collect($required)->every(
            fn (string $term) => $markers->contains(fn (string $marker) => str_contains($marker, $term)),
        );
    }

    private static function containsAny($markers, array $required): bool
    {
        return collect($required)->contains(
            fn (string $term) => $markers->contains(fn (string $marker) => str_contains($marker, $term)),
        );
    }

    private static function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
