<?php

namespace App\Domain\Bioimpedance;

use Illuminate\Support\Str;

final class BodyscanOcrProfile
{
    /**
     * @param  list<string>  $expectedTitles
     * @param  array<string, list<string>>  $metricAliases
     * @param  array<string, list<string>>  $requiredSections
     * @param  list<string>  $availableMetricFields
     * @param  list<string>  $integrityRules
     */
    public function __construct(
        public readonly string $layout,
        public readonly string $application,
        public readonly string $version,
        public readonly string $extractionMode,
        public readonly array $expectedTitles,
        public readonly array $metricAliases,
        public readonly array $requiredSections,
        public readonly array $availableMetricFields,
        public readonly array $integrityRules,
    ) {}

    public function supportsMetric(string $field): bool
    {
        return in_array($field, $this->availableMetricFields, true);
    }

    public function recognizesMetricLabel(string $field, ?string $label): bool
    {
        if (! is_string($label) || trim($label) === '' || ! isset($this->metricAliases[$field])) {
            return false;
        }

        $normalizedLabel = self::normalize($label);

        return collect($this->metricAliases[$field])->contains(
            fn (string $alias) => str_contains($normalizedLabel, self::normalize($alias)),
        );
    }

    /** @return list<string> */
    public function missingRequiredSections(array $detectedSections): array
    {
        $detected = collect($detectedSections)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => self::normalize($value));

        return collect($this->requiredSections)
            ->reject(fn (array $aliases) => collect($aliases)->contains(
                fn (string $alias) => $detected->contains(
                    fn (string $section) => str_contains($section, self::normalize($alias)),
                ),
            ))
            ->keys()
            ->values()
            ->all();
    }

    public function promptBlock(): string
    {
        $titles = collect($this->expectedTitles)->map(fn (string $title) => '- '.$title)->implode("\n");
        $aliases = collect($this->metricAliases)->map(
            fn (array $labels, string $field) => '- '.$field.': '.implode(' | ', $labels),
        )->implode("\n");
        $sections = collect($this->requiredSections)->map(
            fn (array $labels, string $section) => '- '.$section.': '.implode(' | ', $labels),
        )->implode("\n");
        $rules = collect($this->integrityRules)->map(fn (string $rule) => '- '.$rule)->implode("\n");

        return <<<TEXT
Perfil {$this->layout} (versão {$this->version})
Aplicativo: {$this->application}
Modo de extração: {$this->extractionMode}
Títulos esperados:
{$titles}
Aliases de métricas:
{$aliases}
Seções obrigatórias:
{$sections}
Campos disponíveis: {$this->availableMetricFieldsAsText()}
Regras de integridade:
{$rules}
TEXT;
    }

    public function segmentalPromptBlock(): string
    {
        $sections = collect($this->requiredSections)
            ->filter(fn (array $labels, string $section) => str_contains($section, 'segmental') || str_contains($section, 'muscle'))
            ->flatten()
            ->implode(' | ');

        return "Layout: {$this->layout}. Modo visual: {$this->extractionMode}. Seções segmentares: {$sections}.";
    }

    private function availableMetricFieldsAsText(): string
    {
        return implode(', ', $this->availableMetricFields);
    }

    private static function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
