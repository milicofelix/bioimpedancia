<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use App\Models\Bioimpedance\BioimpedanceReportShare;
use App\Models\User;

class BioimpedanceWorkspacePresenter
{
    public function __construct(
        private readonly BioimpedanceReportSharePresenter $reportSharePresenter,
        private readonly RelaxmedicValueNormalizer $relaxmedicValueNormalizer,
        private readonly BioimpedanceAnalyzer $bioimpedanceAnalyzer,
    ) {}

    public function clinic(?BioimpedanceClinicSetting $settings = null): array
    {
        return [
            'name' => config('app.name', 'Clínica'),
            ...($settings ?? BioimpedanceClinicSetting::current())->payload(),
        ];
    }

    public function users(): array
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->user($user))
            ->all();
    }

    public function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_active' => $user->isActive(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
            'inactivated_at' => $user->inactivated_at?->toIso8601String(),
        ];
    }

    public function client(BioimpedanceClient $client): array
    {
        return [
            'id' => $client->id,
            'full_name' => $client->full_name,
            'birth_date' => $client->birth_date?->toDateString(),
            'age' => $client->birth_date?->age,
            'biological_sex' => $client->biological_sex,
            'height_cm' => (float) $client->height_cm,
            'phone' => $client->phone,
            'email' => $client->email,
            'cpf' => $client->cpf,
            'address' => $client->address,
            'emergency_contact_name' => $client->emergency_contact_name,
            'emergency_contact_phone' => $client->emergency_contact_phone,
            'consent_accepted_at' => $client->consent_accepted_at?->toIso8601String(),
            'next_assessment_at' => $client->next_assessment_at?->toDateString(),
            'inactivated_at' => $client->inactivated_at?->toIso8601String(),
            'privacy_exported_at' => $client->privacy_exported_at?->toIso8601String(),
            'privacy_export_count' => $client->privacy_export_count,
            'anonymized_at' => $client->anonymized_at?->toIso8601String(),
            'is_anonymized' => $client->anonymized_at !== null,
            'is_active' => $client->inactivated_at === null,
            'last_assessment_at' => $client->assessments->max('evaluated_at')?->toIso8601String(),
            'notes' => $client->notes,
            'assessments' => $client->assessments
                ->sortByDesc('evaluated_at')
                ->values()
                ->map(fn (BioimpedanceAssessment $assessment) => $this->assessment($assessment))
                ->all(),
        ];
    }

    public function loadClientAssessments(BioimpedanceClient $client): BioimpedanceClient
    {
        return $client->load(['assessments' => fn ($query) => $query
            ->with(['shares', 'careGuidanceApprovedBy', 'aiAnalysisRequests.output'])
            ->latest('evaluated_at')]);
    }

    public function workspace(User $currentUser): array
    {
        $clients = BioimpedanceClient::query()
            ->with(['assessments' => fn ($query) => $query
                ->with(['shares', 'careGuidanceApprovedBy', 'aiAnalysisRequests.output'])
                ->latest('evaluated_at')])
            ->orderBy('full_name')
            ->get()
            ->map(fn (BioimpedanceClient $client) => $this->client($client));

        return [
            'clients' => $clients,
            'clinic' => $this->clinic(),
            'current_user' => $this->user($currentUser),
        ];
    }

    public function assessment(BioimpedanceAssessment $assessment): array
    {
        $analysis = $assessment->analysis;
        if ($assessment->device_model === 'Relaxmedic'
            && ($analysis['reference']['classification_version'] ?? null) !== BioimpedanceAnalyzer::REFERENCE_VERSION) {
            $analysis = $this->bioimpedanceAnalyzer->analyze(
                $assessment->client->toArray(),
                $assessment->toArray(),
            );
        }

        $approvedObservationOutput = $assessment->relationLoaded('aiAnalysisRequests')
            ? $assessment->aiAnalysisRequests
                ->filter(fn ($request) => $request->purpose === 'observation' && $request->output?->approved_at !== null)
                ->sortByDesc(fn ($request) => $request->output->approved_at)
                ->first()?->output
            : BioimpedanceAiAnalysisOutput::query()
                ->with('request')
                ->whereNotNull('approved_at')
                ->whereHas('request', fn ($query) => $query
                    ->where('bioimpedance_assessment_id', $assessment->id)
                    ->where('purpose', 'observation'))
                ->latest('approved_at')
                ->first();
        $hasApprovedAiObservation = $approvedObservationOutput !== null;
        $generatedObservation = data_get($approvedObservationOutput?->raw_response, 'professional_observation');
        $observationWasEdited = $hasApprovedAiObservation
            && filled($generatedObservation)
            && trim((string) $generatedObservation) !== trim((string) $assessment->notes);
        $observationOrigin = match (true) {
            blank($assessment->notes) => 'none',
            ! $hasApprovedAiObservation => 'professional',
            $observationWasEdited => 'ai_edited_and_approved',
            default => 'ai_approved',
        };

        return [
            'id' => $assessment->id,
            'bioimpedance_client_id' => $assessment->bioimpedance_client_id,
            'professional_name' => $assessment->professional?->name,
            'corrected_by_name' => $assessment->correctedBy?->name,
            'canceled_by_name' => $assessment->canceledBy?->name,
            'evaluated_at' => $assessment->evaluated_at?->toIso8601String(),
            'age_at_assessment' => $assessment->age_at_assessment,
            'height_cm_at_assessment' => $assessment->height_cm_at_assessment === null ? null : (float) $assessment->height_cm_at_assessment,
            'biological_sex_at_assessment' => $assessment->biological_sex_at_assessment,
            'device_model' => $assessment->device_model,
            'reference_version' => $assessment->reference_version,
            'weight_kg' => (float) $assessment->weight_kg,
            'scale_bmi' => $assessment->scale_bmi === null ? null : (float) $assessment->scale_bmi,
            'calculated_bmi' => (float) $assessment->calculated_bmi,
            'bmi_difference' => $assessment->bmi_difference === null ? null : (float) $assessment->bmi_difference,
            'body_fat_percentage' => $assessment->body_fat_percentage === null ? null : (float) $assessment->body_fat_percentage,
            'skeletal_muscle_percentage' => $assessment->skeletal_muscle_percentage === null ? null : (float) $assessment->skeletal_muscle_percentage,
            'muscle_rate_percentage' => $assessment->muscle_rate_percentage === null ? null : (float) $assessment->muscle_rate_percentage,
            'lean_body_mass_kg' => $assessment->lean_body_mass_kg === null ? null : (float) $assessment->lean_body_mass_kg,
            'subcutaneous_fat_percentage' => $assessment->subcutaneous_fat_percentage === null ? null : (float) $assessment->subcutaneous_fat_percentage,
            'body_water_percentage' => $assessment->body_water_percentage === null ? null : (float) $assessment->body_water_percentage,
            'muscle_mass_kg' => $assessment->muscle_mass_kg === null ? null : (float) $assessment->muscle_mass_kg,
            'bone_mass_kg' => $assessment->bone_mass_kg === null ? null : (float) $assessment->bone_mass_kg,
            'protein_percentage' => $assessment->protein_percentage === null ? null : (float) $assessment->protein_percentage,
            'fat_mass_kg' => $assessment->fat_mass_kg === null ? null : (float) $assessment->fat_mass_kg,
            'water_weight_kg' => $assessment->water_weight_kg === null ? null : (float) $assessment->water_weight_kg,
            'protein_mass_kg' => $assessment->protein_mass_kg === null ? null : (float) $assessment->protein_mass_kg,
            'ideal_body_weight_kg' => $assessment->ideal_body_weight_kg === null ? null : (float) $assessment->ideal_body_weight_kg,
            'obesity_level' => $assessment->device_model === 'Relaxmedic'
                ? $this->relaxmedicValueNormalizer->obesityLevel($assessment->obesity_level)
                : $assessment->obesity_level,
            'body_type' => $assessment->body_type,
            'resting_metabolism_kcal' => $assessment->resting_metabolism_kcal,
            'body_age' => $assessment->body_age,
            'visceral_fat_level' => $assessment->visceral_fat_level === null ? null : (float) $assessment->visceral_fat_level,
            'analysis' => $analysis,
            'source_metadata' => $assessment->source_metadata,
            'notes' => $assessment->notes,
            'care_guidance' => $assessment->care_guidance,
            'care_guidance_sources' => $assessment->care_guidance_sources ?? [],
            'care_guidance_approved_at' => $assessment->care_guidance_approved_at?->toIso8601String(),
            'care_guidance_approved_by_name' => $assessment->careGuidanceApprovedBy?->name,
            'has_approved_ai_observation' => $hasApprovedAiObservation,
            'observation_origin' => $observationOrigin,
            'correction_count' => $assessment->correction_count,
            'canceled_at' => $assessment->canceled_at?->toIso8601String(),
            'cancellation_reason' => $assessment->cancellation_reason,
            'is_canceled' => $assessment->canceled_at !== null,
            'report_issued_at' => $assessment->report_issued_at?->toIso8601String(),
            'report_issue_count' => $assessment->report_issue_count,
            'shares' => $assessment->relationLoaded('shares')
                ? $assessment->shares->sortByDesc('created_at')->values()->map(
                    fn (BioimpedanceReportShare $share) => $this->reportSharePresenter->payload($share),
                )->all()
                : [],
        ];
    }
}
