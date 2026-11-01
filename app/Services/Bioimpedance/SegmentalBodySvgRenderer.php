<?php

namespace App\Services\Bioimpedance;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Produces a self-contained, DomPDF-safe rendering of the Vita Scan body map.
 *
 * The vector geometry remains owned by the shared SVG asset used by the web
 * interface. This renderer only adds the state-specific presentation layer.
 */
class SegmentalBodySvgRenderer
{
    private const REGIONS = [
        'arm|left' => 'region-arm-left',
        'arm|right' => 'region-arm-right',
        'trunk|center' => 'region-trunk-center',
        'leg|left' => 'region-leg-left',
        'leg|right' => 'region-leg-right',
    ];

    private const STATES = [
        'low' => ['dash' => '7 4'],
        'standard' => ['dash' => 'none'],
        'high' => ['dash' => '2 2'],
        'unknown' => ['dash' => '10 4 2 4'],
    ];

    public function renderPair(array $measurements): array
    {
        return [
            'fat' => $this->renderDataUri($measurements, 'fat'),
            'muscle' => $this->renderDataUri($measurements, 'muscle'),
        ];
    }

    public function renderDataUri(array $measurements, string $type): string
    {
        $svg = $this->sourceSvg();
        $measurementsByRegion = Collection::make($measurements)
            ->where('type', $type)
            ->keyBy(fn (array $measurement): string => ($measurement['region'] ?? '').'|'.($measurement['side'] ?? ''));

        $svg = preg_replace('/<style>.*?<\/style>/s', '', $svg) ?? $svg;
        $svg = str_replace('class="body-region"', 'class="body-region" fill="#ffffff" stroke="#111827" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"', $svg);
        $svg = str_replace('class="body-detail"', 'class="body-detail" fill="none" stroke="#6b7280" stroke-width="1.25" stroke-linecap="round"', $svg);
        $svg = str_replace('class="body-separator"', 'class="body-separator" fill="none" stroke="#6b7280" stroke-width="1.2"', $svg);
        $svg = str_replace('class="annotation-anchor"', 'class="annotation-anchor" fill="none" opacity="0"', $svg);

        foreach (self::REGIONS as $key => $pathId) {
            $measurement = $measurementsByRegion->get($key, []);
            $classification = $measurement['classification'] ?? 'unknown';
            $stateKey = array_key_exists($classification, self::STATES) ? $classification : 'unknown';
            $state = self::STATES[$stateKey];
            if ($state['dash'] !== 'none') {
                $svg = str_replace('id="'.$pathId.'"', 'id="'.$pathId.'" stroke-dasharray="'.$state['dash'].'"', $svg);
            }
        }

        $presentation = '<g id="pdf-side-markers" aria-hidden="true">'
            .'<text x="111" y="397" fill="#111827" font-family="DejaVu Sans, sans-serif" font-size="18" font-weight="700">L</text>'
            .'<text x="241" y="397" fill="#111827" font-family="DejaVu Sans, sans-serif" font-size="18" font-weight="700">R</text>'
            .'</g>';

        $svg = str_replace('</svg>', $presentation.'</svg>', $svg);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function sourceSvg(): string
    {
        $path = resource_path('images/bioimpedance/vita-scan-segmental-body-front.svg');
        $svg = is_readable($path) ? file_get_contents($path) : false;

        if (! is_string($svg) || $svg === '') {
            throw new RuntimeException('O ativo vetorial do mapa corporal Vita Scan não está disponível.');
        }

        foreach (self::REGIONS as $pathId) {
            if (! str_contains($svg, 'id="'.$pathId.'"')) {
                throw new RuntimeException('O ativo vetorial do mapa corporal Vita Scan está incompleto.');
            }
        }

        return $svg;
    }

}
