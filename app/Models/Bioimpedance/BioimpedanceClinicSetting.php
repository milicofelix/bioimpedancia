<?php

namespace App\Models\Bioimpedance;

use Illuminate\Database\Eloquent\Model;

class BioimpedanceClinicSetting extends Model
{
    public const SCALE_MODEL_OMRON_HBF_514C = 'omron_hbf_514c';

    public const SCALE_MODEL_RELAXMEDIC = 'relaxmedic';

    public const SCALE_MODELS = [
        self::SCALE_MODEL_OMRON_HBF_514C,
        self::SCALE_MODEL_RELAXMEDIC,
    ];

    public const DEFAULTS = [
        'display_name' => 'Ricosty Emagrecimento e Estética',
        'legal_name' => 'Ricosty Emagrecimento e Estética',
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
        'footer_text' => 'Ricosty Emagrecimento e Estética - Avaliação corporal e acompanhamento estético',
        'technical_notice' => 'Os resultados de bioimpedância são estimativas e podem variar conforme hidratação, alimentação, ciclo hormonal, medicamentos e condições de medição. Este documento não substitui avaliação médica ou nutricional.',
        'scale_model' => self::SCALE_MODEL_OMRON_HBF_514C,
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
    ];

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
        ];
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
