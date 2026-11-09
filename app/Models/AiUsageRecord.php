<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageRecord extends Model
{
    public const HISTORICAL_OPENING_OPERATION = 'historical_opening';

    public const OPERATIONS = [
        self::HISTORICAL_OPENING_OPERATION => 'Consumo histórico consolidado',
        'bodyscan_ocr' => 'OCR Bodyscan',
        'bodyscan_segmental_ocr' => 'OCR segmentar — segunda leitura',
        'relaxmedic_ocr' => 'OCR Relaxmedic',
        'body_observation' => 'Agente 1 — Análise corporal',
        'care_guidance' => 'Agente 2 — Próximos cuidados',
    ];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'array',
            'estimated_cost_usd' => 'decimal:12',
            'created_at' => 'datetime',
        ];
    }
}
