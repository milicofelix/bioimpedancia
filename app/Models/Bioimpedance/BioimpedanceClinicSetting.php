<?php

namespace App\Models\Bioimpedance;

use App\Domain\Bioimpedance\ScaleModel;
use Illuminate\Database\Eloquent\Model;

class BioimpedanceClinicSetting extends Model
{
    public const MODULE_ASSESSMENT = 'assessment';

    public const MODULE_SCREENING = 'screening';

    public const MODULE_REPORTS = 'reports';

    public const SCALE_MODEL_OMRON_HBF_514C = ScaleModel::OMRON_HBF_514C;

    public const SCALE_MODEL_RELAXMEDIC = ScaleModel::RELAXMEDIC;

    public const SCALE_MODEL_BODYSCAN_PRO = ScaleModel::BODYSCAN_PRO;

    public const SCALE_MODELS = ScaleModel::MODELS;

    public const SCALE_MODEL_NAMES = ScaleModel::NAMES;

    public const DEFAULTS = [
        'display_name' => 'Vita Scan Análise clínica Biológica',
        'legal_name' => 'Vita Scan Análise clínica Biológica',
        'document' => null,
        'phone' => null,
        'whatsapp' => null,
        'email' => null,
        'address' => null,
        'instagram' => null,
        'website' => null,
        'primary_color' => '#d88b9a',
        'secondary_color' => '#4a4a4a',
        'logo_url' => '/images/brand/ricosty-logo.png',
        'contact' => 'Avaliação corporal e acompanhamento estético',
        'footer_text' => 'Vita Scan Análise clínica Biológica - Avaliação corporal e acompanhamento estético',
        'technical_notice' => 'Os resultados de bioimpedância são estimativas e podem variar conforme hidratação, alimentação, ciclo hormonal, medicamentos e condições de medição. Este documento não substitui avaliação médica ou nutricional.',
        'scale_model' => self::SCALE_MODEL_BODYSCAN_PRO,
        'assessment_module_enabled' => true,
        'screening_module_enabled' => true,
        'reports_module_enabled' => true,
    ];

    protected $fillable = [
        'display_name',
        'legal_name',
        'document',
        'phone',
        'whatsapp',
        'email',
        'address',
        'instagram',
        'website',
        'primary_color',
        'secondary_color',
        'logo_url',
        'contact',
        'footer_text',
        'technical_notice',
        'scale_model',
        'assessment_module_enabled',
        'screening_module_enabled',
        'reports_module_enabled',
    ];

    protected function casts(): array
    {
        return [
            'assessment_module_enabled' => 'boolean',
            'screening_module_enabled' => 'boolean',
            'reports_module_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], self::DEFAULTS);
    }

    public function payload(): array
    {
        return [
            ...self::DEFAULTS,
            ...$this->only($this->fillable),
            'logo_initials' => $this->initials(),
            'scale_model_name' => $this->scaleModelName(),
            'scale_capabilities' => $this->scaleCapabilities(),
            'scale_reference_version' => $this->scaleReferenceVersion(),
            'enabled_modules' => $this->enabledModules(),
        ];
    }

    public function moduleEnabled(string $module): bool
    {
        return match ($module) {
            self::MODULE_ASSESSMENT => (bool) $this->assessment_module_enabled,
            self::MODULE_SCREENING => (bool) $this->screening_module_enabled,
            self::MODULE_REPORTS => (bool) $this->reports_module_enabled,
            default => false,
        };
    }

    /** @return array{assessment: bool, screening: bool, reports: bool} */
    public function enabledModules(): array
    {
        return [
            self::MODULE_ASSESSMENT => $this->moduleEnabled(self::MODULE_ASSESSMENT),
            self::MODULE_SCREENING => $this->moduleEnabled(self::MODULE_SCREENING),
            self::MODULE_REPORTS => $this->moduleEnabled(self::MODULE_REPORTS),
        ];
    }

    public function scaleModelName(): string
    {
        return ScaleModel::name($this->scale_model);
    }

    /** @return list<string> */
    public function scaleCapabilities(): array
    {
        return ScaleModel::capabilities($this->scale_model);
    }

    public function scaleSupports(string $capability): bool
    {
        return ScaleModel::supports($this->scale_model, $capability);
    }

    public function scaleReferenceVersion(): string
    {
        return ScaleModel::referenceVersion($this->scale_model);
    }

    private function initials(): string
    {
        $words = collect(explode(' ', (string) $this->display_name))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_substr($word, 0, 1));

        return $words->implode('') ?: 'RS';
    }
}
