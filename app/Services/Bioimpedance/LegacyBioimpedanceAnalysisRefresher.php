<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceClient;
use Illuminate\Support\Carbon;

class LegacyBioimpedanceAnalysisRefresher
{
    public function __construct(private readonly BioimpedanceAnalyzer $analyzer) {}

    public function refreshIfLegacy(BioimpedanceAssessment $assessment): bool
    {
        if (! $this->isLegacy($assessment->analysis)) {
            return false;
        }

        $client = $assessment->client;
        $snapshot = $this->snapshot($assessment, $client);
        $assessmentPayload = [
            ...$assessment->toArray(),
            ...$snapshot,
        ];
        $analysis = $this->analyzer->analyze($client->toArray(), $assessmentPayload);

        $assessment->forceFill([
            ...$snapshot,
            'calculated_bmi' => $analysis['calculated_bmi'],
            'bmi_difference' => $analysis['bmi_difference'],
            'analysis' => $analysis,
        ])->saveQuietly();

        return true;
    }

    public function isLegacy(?array $analysis): bool
    {
        if (! $analysis) {
            return true;
        }

        if (($analysis['reference']['classification_version'] ?? null) === null) {
            return true;
        }

        if (str_contains($analysis['summary'] ?? '', 'kg/m2')) {
            return true;
        }

        foreach (['body_fat', 'skeletal_muscle', 'visceral_fat'] as $indicator) {
            if (($analysis['indicators'][$indicator]['classification'] ?? null) === 'Aguardando manual Omron') {
                return true;
            }
        }

        return false;
    }

    private function snapshot(BioimpedanceAssessment $assessment, BioimpedanceClient $client): array
    {
        return [
            'age_at_assessment' => $assessment->age_at_assessment
                ?? (int) $client->birth_date->diffInYears(Carbon::parse($assessment->evaluated_at)),
            'height_cm_at_assessment' => (float) ($assessment->height_cm_at_assessment ?? $client->height_cm),
            'biological_sex_at_assessment' => $assessment->biological_sex_at_assessment ?? $client->biological_sex,
            'device_model' => $assessment->device_model ?? BioimpedanceAnalyzer::DEVICE_MODEL,
            'reference_version' => BioimpedanceAnalyzer::REFERENCE_VERSION,
        ];
    }
}
