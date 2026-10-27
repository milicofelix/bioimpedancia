<?php

namespace App\Models\Bioimpedance;

use App\Domain\Bioimpedance\ScaleModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceSegmentalMeasurement extends Model
{
    public const TYPE_FAT = 'fat';

    public const TYPE_MUSCLE = 'muscle';

    public const TYPES = [
        self::TYPE_FAT,
        self::TYPE_MUSCLE,
    ];

    public const REGION_ARM = 'arm';

    public const REGION_TRUNK = 'trunk';

    public const REGION_LEG = 'leg';

    public const REGIONS = [
        self::REGION_ARM,
        self::REGION_TRUNK,
        self::REGION_LEG,
    ];

    public const SIDE_LEFT = 'left';

    public const SIDE_CENTER = 'center';

    public const SIDE_RIGHT = 'right';

    public const SIDES = [
        self::SIDE_LEFT,
        self::SIDE_CENTER,
        self::SIDE_RIGHT,
    ];

    public const CLASSIFICATION_LOW = 'low';

    public const CLASSIFICATION_STANDARD = 'standard';

    public const CLASSIFICATION_HIGH = 'high';

    public const CLASSIFICATIONS = [
        self::CLASSIFICATION_LOW,
        self::CLASSIFICATION_STANDARD,
        self::CLASSIFICATION_HIGH,
    ];

    public const CLASSIFICATION_LABELS = [
        self::CLASSIFICATION_LOW => 'Baixo',
        self::CLASSIFICATION_STANDARD => 'Padrão',
        self::CLASSIFICATION_HIGH => 'Alto',
    ];

    public const EXPECTED_SEGMENTS = [
        ['region' => self::REGION_ARM, 'side' => self::SIDE_LEFT],
        ['region' => self::REGION_ARM, 'side' => self::SIDE_RIGHT],
        ['region' => self::REGION_TRUNK, 'side' => self::SIDE_CENTER],
        ['region' => self::REGION_LEG, 'side' => self::SIDE_LEFT],
        ['region' => self::REGION_LEG, 'side' => self::SIDE_RIGHT],
    ];

    protected $fillable = [
        'bioimpedance_assessment_id',
        'type',
        'region',
        'side',
        'mass_kg',
        'reference_percentage',
        'classification',
        'reference_min_percentage',
        'reference_max_percentage',
        'reference_version',
        'raw_value',
    ];

    protected function casts(): array
    {
        return [
            'mass_kg' => 'decimal:2',
            'reference_percentage' => 'decimal:2',
            'reference_min_percentage' => 'decimal:2',
            'reference_max_percentage' => 'decimal:2',
            'raw_value' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAssessment::class, 'bioimpedance_assessment_id');
    }

    public static function defaultReferenceVersion(): string
    {
        return ScaleModel::BODYSCAN_REFERENCE_VERSION;
    }
}
