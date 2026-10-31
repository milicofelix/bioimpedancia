<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanReportLayout;

class BodyscanSegmentalImagePreparer
{
    /**
     * Gera ampliações auxiliares somente depois que o layout técnico já foi
     * identificado pelos textos. A imagem integral continua no contexto para
     * preservar os marcadores L/R e a relação entre as duas seções.
     *
     * @return list<array{label: string, contents: string}>
     */
    public function prepare(string $imageContents, ?string $reportLayout): array
    {
        if ($reportLayout !== BodyscanReportLayout::FITDAYS_TECHNICAL
            || ! function_exists('imagecreatefromstring')
            || ! function_exists('imagecrop')
            || ! function_exists('imagejpeg')) {
            return [];
        }

        $source = @imagecreatefromstring($imageContents);
        if ($source === false) {
            return [];
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);

            return collect([
                ['label' => 'ampliação da gordura segmentar', 'x' => 0.02, 'width' => 0.36],
                ['label' => 'ampliação do equilíbrio muscular', 'x' => 0.28, 'width' => 0.38],
            ])->map(function (array $definition) use ($source, $width, $height): ?array {
                $crop = imagecrop($source, [
                    'x' => (int) round($width * $definition['x']),
                    'y' => (int) round($height * 0.50),
                    'width' => (int) round($width * $definition['width']),
                    'height' => (int) round($height * 0.34),
                ]);
                if ($crop === false) {
                    return null;
                }

                ob_start();
                $encoded = imagejpeg($crop, null, 94);
                $contents = ob_get_clean();
                imagedestroy($crop);

                if (! $encoded || ! is_string($contents) || $contents === '') {
                    return null;
                }

                return ['label' => $definition['label'], 'contents' => $contents];
            })->filter()->values()->all();
        } finally {
            imagedestroy($source);
        }
    }
}
