<?php

namespace App\Models\Bioimpedance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioimpedanceExtendedMetric extends Model
{
    protected $fillable = [
        'bioimpedance_assessment_id',
        'metric_code',
        'original_label',
        'numeric_value',
        'text_value',
        'unit',
        'application_classification',
        'printed_minimum',
        'printed_maximum',
        'weight_proportion_percentage',
        'source_application',
        'source_layout',
        'raw_extracted_value',
        'reference_version',
    ];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'decimal:4',
            'printed_minimum' => 'decimal:4',
            'printed_maximum' => 'decimal:4',
            'weight_proportion_percentage' => 'decimal:3',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BioimpedanceAssessment::class, 'bioimpedance_assessment_id');
    }
}
