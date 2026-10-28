<?php

namespace App\Domain\Bioimpedance;

final class ScaleModel
{
    public const OMRON_HBF_514C = 'omron_hbf_514c';

    public const RELAXMEDIC = 'relaxmedic';

    public const BODYSCAN_PRO = 'relaxmedic_bodyscan_pro';

    public const OMRON_NAME = 'HBF-514C';

    public const RELAXMEDIC_NAME = 'Relaxmedic';

    public const BODYSCAN_PRO_NAME = 'Bodyscan Pro';

    public const BODYSCAN_PRO_MODEL_CODE = 'RM-BD0015A';

    public const BODYSCAN_PRO_MANUAL_VERSION = 'V1.1222';

    public const BODYSCAN_APPLICATION_RELAXFIT = 'relaxfit';

    public const BODYSCAN_APPLICATION_FITDAYS = 'fitdays';

    public const BODYSCAN_APPLICATIONS = [
        self::BODYSCAN_APPLICATION_RELAXFIT,
        self::BODYSCAN_APPLICATION_FITDAYS,
    ];

    public const BODYSCAN_PROTOCOL_KEYS = [
        'profile_confirmed',
        'athlete_mode_confirmed',
        'electrodes_contact_confirmed',
        'comparable_conditions_confirmed',
    ];

    public const CAPABILITY_MANUAL_ENTRY = 'manual_entry';

    public const CAPABILITY_IMAGE_IMPORT = 'image_import';

    public const CAPABILITY_EXTENDED_METRICS = 'extended_metrics';

    public const CAPABILITY_SEGMENTAL_ANALYSIS = 'segmental_analysis';

    public const OMRON_REFERENCE_VERSION = '1.1.0';

    public const RELAXMEDIC_REFERENCE_VERSION = '1.1.0';

    public const BODYSCAN_REFERENCE_VERSION = 'bodyscan-pro-1.0.0';

    public const MODELS = [
        self::OMRON_HBF_514C,
        self::RELAXMEDIC,
        self::BODYSCAN_PRO,
    ];

    public const NAMES = [
        self::OMRON_HBF_514C => self::OMRON_NAME,
        self::RELAXMEDIC => self::RELAXMEDIC_NAME,
        self::BODYSCAN_PRO => self::BODYSCAN_PRO_NAME,
    ];

    private const REFERENCE_VERSIONS = [
        self::OMRON_HBF_514C => self::OMRON_REFERENCE_VERSION,
        self::RELAXMEDIC => self::RELAXMEDIC_REFERENCE_VERSION,
        self::BODYSCAN_PRO => self::BODYSCAN_REFERENCE_VERSION,
    ];

    private const CAPABILITIES = [
        self::OMRON_HBF_514C => [
            self::CAPABILITY_MANUAL_ENTRY,
        ],
        self::RELAXMEDIC => [
            self::CAPABILITY_IMAGE_IMPORT,
            self::CAPABILITY_EXTENDED_METRICS,
        ],
        self::BODYSCAN_PRO => [
            self::CAPABILITY_IMAGE_IMPORT,
            self::CAPABILITY_EXTENDED_METRICS,
            self::CAPABILITY_SEGMENTAL_ANALYSIS,
        ],
    ];

    public static function name(string $model): string
    {
        return self::NAMES[$model] ?? self::NAMES[self::BODYSCAN_PRO];
    }

    public static function referenceVersion(string $model): string
    {
        return self::REFERENCE_VERSIONS[$model] ?? self::BODYSCAN_REFERENCE_VERSION;
    }

    /** @return list<string> */
    public static function capabilities(string $model): array
    {
        return self::CAPABILITIES[$model] ?? [];
    }

    public static function supports(string $model, string $capability): bool
    {
        return in_array($capability, self::capabilities($model), true);
    }
}
