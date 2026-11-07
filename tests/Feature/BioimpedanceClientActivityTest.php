<?php

namespace Tests\Feature;

use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Screening\ScreeningQuestionnaire;
use App\Models\Screening\ScreeningSession;
use App\Services\Bioimpedance\BioimpedanceClientActivity;
use Database\Seeders\ScreeningQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioimpedanceClientActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_import_and_correction_count_without_changing_the_clinical_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        $client = $this->client();
        $this->travel(6)->days();
        $assessment = $client->assessments()->create([
            'evaluated_at' => '2026-10-01 09:00:00',
            'weight_kg' => 80,
            'calculated_bmi' => 26.4,
        ]);
        $service = app(BioimpedanceClientActivity::class);
        $this->assertSame(now()->toIso8601String(), $service->latest($client->fresh()));

        $this->travel(1)->hours();
        $assessment->update(['notes' => 'Correção registrada pelo profissional.']);
        $this->assertSame(now()->toIso8601String(), $service->latest($client->fresh()));
        $this->assertSame('2026-10-01 09:00:00', $assessment->fresh()->evaluated_at->toDateTimeString());
    }

    public function test_screening_creation_and_finalization_survive_reloading_without_bioimpedance(): void
    {
        $this->seed(ScreeningQuestionnaireSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        $client = $this->client();
        $this->travel(6)->days();
        $questionnaire = ScreeningQuestionnaire::query()->firstOrFail();
        $session = ScreeningSession::query()->create([
            'bioimpedance_client_id' => $client->id,
            'screening_questionnaire_id' => $questionnaire->id,
            'questionnaire_code' => $questionnaire->code,
            'questionnaire_version' => $questionnaire->version,
            'questionnaire_name' => $questionnaire->name,
            'consent_text_snapshot' => $questionnaire->consent_text,
            'disclaimer_snapshot' => $questionnaire->disclaimer,
            'status' => ScreeningSession::STATUS_PENDING,
        ]);
        $service = app(BioimpedanceClientActivity::class);
        $this->assertSame(now()->toIso8601String(), $service->latest($client->fresh()));

        $this->travel(1)->hours();
        $session->update(['status' => ScreeningSession::STATUS_FINALIZED, 'finalized_at' => now()]);
        $reloaded = $service->withScreeningActivity(BioimpedanceClient::query())
            ->with('assessments')->findOrFail($client->id);
        $this->assertArrayHasKey('latest_screening_updated_at', $reloaded->getAttributes());
        $this->assertSame(now()->toIso8601String(), $service->latest($reloaded));
        $this->assertCount(0, $reloaded->assessments);
    }

    public function test_registration_and_profile_changes_count_as_activity(): void
    {
        $client = $this->client();
        $service = app(BioimpedanceClientActivity::class);
        $this->assertSame($client->created_at->toIso8601String(), $service->latest($client));
        $this->travel(1)->days();
        $client->update(['height_cm' => 175]);
        $this->assertSame($client->updated_at->toIso8601String(), $service->latest($client->fresh()));
    }

    private function client(): BioimpedanceClient
    {
        return BioimpedanceClient::query()->create([
            'full_name' => 'Cliente de regressão',
            'birth_date' => '1980-01-01',
            'biological_sex' => 'male',
            'height_cm' => 174,
        ]);
    }
}
