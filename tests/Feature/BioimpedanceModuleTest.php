<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioimpedanceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_client_and_assessment(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Maria Silva',
            'birth_date' => '1987-05-20',
            'biological_sex' => 'female',
            'height_cm' => 174,
            'phone' => '(11) 99999-9999',
            'email' => 'maria@example.com',
        ]);

        $clientResponse->assertCreated();
        $clientId = $clientResponse->json('client.id');

        $assessmentResponse = $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientId,
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 90.2,
            'scale_bmi' => 29.8,
            'body_fat_percentage' => 28.4,
            'skeletal_muscle_percentage' => 31.2,
            'resting_metabolism_kcal' => 1780,
            'body_age' => 47,
            'visceral_fat_level' => 12,
        ]);

        $assessmentResponse->assertCreated();
        $assessmentResponse->assertJsonPath('assessment.calculated_bmi', 29.8);
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.bmi.classification', 'Sobrepeso');
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.body_fat.classification', 'Aguardando manual Omron');
    }

    public function test_client_height_can_be_sent_in_meters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Adriano Freitas',
            'birth_date' => '1981-07-03',
            'biological_sex' => 'male',
            'height_cm' => '1.74',
            'phone' => '11951410140',
            'email' => 'milicofelix@gmail.com',
            'notes' => 'Nao deve ser obrigatorio',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('client.height_cm', 174);
    }

    public function test_guest_cannot_access_bioimpedance_data(): void
    {
        $this->getJson(route('bioimpedance.index'))->assertUnauthorized();
    }
}
