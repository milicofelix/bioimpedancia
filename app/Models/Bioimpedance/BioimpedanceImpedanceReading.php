<?php

namespace App\Models\Bioimpedance;

use App\Domain\Bioimpedance\ScaleModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceImpedanceReading extends Model
{
    public const FREQUENCIES_KHZ = [20, 100];

    public const REGIONS = ['arm', 'trunk', 'leg'];

    public const SIDES = ['left', 'center', 'right'];

    public const STATES = ['clear', 'uncertain', 'unreadable'];

    public const EXPECTED_POSITIONS = [
        ['region' => 'arm', 'side' => 'right'],
        ['region' => 'arm', 'side' => 'left'],
        ['region' => 'trunk', 'side' => 'center'],
        ['region' => 'leg', 'side' => 'right'],
        ['region' => 'leg', 'side' => 'left'],
    ];

    protected $fillable = [
        'bioimpedance_assessment_id',
        'frequency_khz',
        'region',
        'side',
        'impedance_ohms',
        'raw_value',
        'reading_state',
        'device_version',
        'source_application',
        'source_layout',
    ];

    protected function casts(): array
    {
        return [
            'frequency_khz' => 'integer',
            'impedance_ohms' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAssessment::class, 'bioimpedance_assessment_id');
    }

    public static function deviceVersion(): string
    {
        return ScaleModel::BODYSCAN_PRO_MODEL_CODE.'-dual-frequency';
    }
}
