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
        'low' => ['color' => '#2563eb', 'fill' => '#dbeafe', 'muscle_fill' => '#93c5fd'],
        'standard' => ['color' => '#047857', 'fill' => '#d1fae5', 'muscle_fill' => '#6ee7b7'],
        'high' => ['color' => '#be123c', 'fill' => '#ffe4e6', 'muscle_fill' => '#fda4af'],
        'unknown' => ['color' => '#475569', 'fill' => '#e2e8f0', 'muscle_fill' => '#cbd5e1'],
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

        $styles = [];
        foreach (self::REGIONS as $key => $pathId) {
            $measurement = $measurementsByRegion->get($key, []);
            $classification = $measurement['classification'] ?? 'unknown';
            $stateKey = array_key_exists($classification, self::STATES) ? $classification : 'unknown';
            $state = self::STATES[$stateKey];
            $fill = $type === 'muscle'
                ? $state['muscle_fill']
                : "url(#vita-fat-{$stateKey}) {$state['fill']}";
            $dash = $type === 'fat' ? 'stroke-dasharray:3 1.5;' : '';

            $styles[] = "#{$pathId}{fill:{$fill}!important;stroke:{$state['color']}!important;{$dash}}";
        }

        $definitions = $this->fatPatterns();
        $presentation = '<g id="pdf-side-markers" aria-hidden="true">'
            .'<text x="111" y="397" fill="#7f1d3f" font-family="DejaVu Sans, sans-serif" font-size="18" font-weight="700">L</text>'
            .'<text x="241" y="397" fill="#7f1d3f" font-family="DejaVu Sans, sans-serif" font-size="18" font-weight="700">R</text>'
            .'</g>';

        $svg = str_replace('</style>', implode('', $styles).'</style>'.$definitions, $svg);
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

    private function fatPatterns(): string
    {
        $patterns = [];

        foreach (self::STATES as $key => $state) {
            $patterns[] = '<pattern id="vita-fat-'.$key.'" patternUnits="userSpaceOnUse" width="8" height="8">'
                .'<rect width="8" height="8" fill="'.$state['fill'].'"/>'
                .'<path d="M-2 2L2-2M0 8L8 0M6 10L10 6" fill="none" stroke="'.$state['color'].'" stroke-width="0.8" opacity="0.38"/>'
                .'</pattern>';
        }

        return '<defs>'.implode('', $patterns).'</defs>';
    }
}
