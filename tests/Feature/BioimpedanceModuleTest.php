<?php

namespace Tests\Feature;

use App\Models\Bioimpedance\BioimpedanceClient;
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
        $assessmentResponse->assertJsonPath('assessment.age_at_assessment', 39);
        $assessmentResponse->assertJsonPath('assessment.height_cm_at_assessment', 174);
        $assessmentResponse->assertJsonPath('assessment.biological_sex_at_assessment', 'female');
        $assessmentResponse->assertJsonPath('assessment.device_model', 'HBF-514C');
        $assessmentResponse->assertJsonPath('assessment.reference_version', '1.0.0');
        $assessmentResponse->assertJsonPath('assessment.calculated_bmi', 29.8);
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.bmi.classification', 'Sobrepeso');
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.body_fat.classification', 'Normal');
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.skeletal_muscle.classification', 'Alto');
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.visceral_fat.classification', 'Elevada');
    }

    public function test_hbf_514c_tables_classify_male_assessment_by_age(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Adriano Freitas',
            'birth_date' => '1981-07-03',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'email' => 'adriano@example.com',
        ]);

        $assessmentResponse = $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 23:19:00',
            'weight_kg' => 95.2,
            'scale_bmi' => 31.4,
            'body_fat_percentage' => 20.5,
            'skeletal_muscle_percentage' => 37.6,
            'resting_metabolism_kcal' => 1935,
            'body_age' => 64,
            'visceral_fat_level' => 14,
        ]);

        $assessmentResponse->assertCreated();
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.body_fat.classification', 'Normal');
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.skeletal_muscle.classification', 'Normal');
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.visceral_fat.classification', 'Elevada');
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.body_fat.pending', false);
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

    public function test_client_can_be_updated_with_management_fields(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Cliente Editavel',
            'birth_date' => '1991-04-10',
            'biological_sex' => 'female',
            'height_cm' => 165,
            'phone' => '(11) 90000-0001',
            'email' => 'cliente.editavel@example.com',
        ]);

        $response = $this->actingAs($user)->putJson(route('bioimpedance.clients.update', $clientResponse->json('client.id')), [
            'full_name' => 'Cliente Editada',
            'birth_date' => '1991-04-10',
            'biological_sex' => 'female',
            'height_cm' => '1,66',
            'phone' => '(11) 90000-0002',
            'email' => 'cliente.editada@example.com',
            'cpf' => '123.456.789-01',
            'address' => 'Rua das Flores, 123',
            'emergency_contact_name' => 'Contato Familiar',
            'emergency_contact_phone' => '(11) 98888-7777',
            'consent_accepted' => true,
            'next_assessment_at' => now()->addMonth()->toDateString(),
            'notes' => 'Cadastro revisado.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('client.full_name', 'Cliente Editada');
        $response->assertJsonPath('client.height_cm', 166);
        $response->assertJsonPath('client.cpf', '12345678901');
        $response->assertJsonPath('client.address', 'Rua das Flores, 123');
        $response->assertJsonPath('client.emergency_contact_name', 'Contato Familiar');
        $response->assertJsonPath('client.emergency_contact_phone', '11988887777');
        $this->assertNotNull($response->json('client.consent_accepted_at'));
    }

    public function test_client_duplicate_contact_data_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Cliente Original',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'phone' => '(11) 95555-0000',
            'email' => 'duplicado@example.com',
            'cpf' => '111.222.333-44',
        ])->assertCreated();

        $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Cliente Duplicado',
            'birth_date' => '1992-01-01',
            'biological_sex' => 'female',
            'height_cm' => 164,
            'phone' => '11955550000',
            'email' => 'duplicado@example.com',
            'cpf' => '11122233344',
        ])->assertJsonValidationErrors(['phone_digits', 'email', 'cpf']);
    }

    public function test_client_can_be_inactivated_without_losing_history(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Cliente Inativado',
            'birth_date' => '1988-01-01',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'email' => 'cliente.inativado@example.com',
        ]);

        $assessmentPayload = [
            'bioimpedance_client_id' => $clientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 80,
            'scale_bmi' => 26.4,
            'body_fat_percentage' => 20,
            'skeletal_muscle_percentage' => 37,
            'resting_metabolism_kcal' => 1800,
            'body_age' => 42,
            'visceral_fat_level' => 9,
        ];

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), $assessmentPayload)
            ->assertCreated();

        $this->actingAs($user)->patchJson(route('bioimpedance.clients.inactivate', $clientResponse->json('client.id')))
            ->assertOk()
            ->assertJsonPath('client.is_active', false)
            ->assertJsonCount(1, 'client.assessments');

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            ...$assessmentPayload,
            'evaluated_at' => '2026-08-08 09:30:00',
        ])->assertJsonValidationErrors(['bioimpedance_client_id']);
    }

    public function test_assessment_age_is_calculated_from_evaluation_date(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Historico Faixa Etaria',
            'birth_date' => '1966-08-08',
            'biological_sex' => 'male',
            'height_cm' => 170,
            'email' => 'historico@example.com',
        ]);

        $assessmentResponse = $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 70,
            'scale_bmi' => 24.2,
            'body_fat_percentage' => 12,
            'skeletal_muscle_percentage' => 33,
            'resting_metabolism_kcal' => 1600,
            'body_age' => 45,
            'visceral_fat_level' => 9,
        ]);

        $assessmentResponse->assertCreated();
        $assessmentResponse->assertJsonPath('assessment.age_at_assessment', 59);
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.body_fat.classification', 'Normal');
    }

    public function test_stored_assessment_analysis_is_not_recalculated_when_client_changes(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Analise Congelada',
            'birth_date' => '1980-01-01',
            'biological_sex' => 'female',
            'height_cm' => 160,
            'email' => 'analise.congelada@example.com',
        ]);

        $assessmentResponse = $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientResponse->json('client.id'),
            'evaluated_at' => '2026-03-01 09:30:00',
            'weight_kg' => 70,
            'scale_bmi' => 27.3,
            'body_fat_percentage' => 22,
            'skeletal_muscle_percentage' => 29,
            'resting_metabolism_kcal' => 1450,
            'body_age' => 43,
            'visceral_fat_level' => 9,
        ]);

        $assessmentResponse->assertCreated();
        $assessmentResponse->assertJsonPath('assessment.analysis.indicators.body_fat.classification', 'Baixa');

        BioimpedanceClient::query()->find($clientResponse->json('client.id'))->update([
            'biological_sex' => 'male',
            'height_cm' => 180,
        ]);

        $this->actingAs($user)
            ->getJson(route('bioimpedance.index'))
            ->assertOk()
            ->assertJsonPath('clients.0.assessments.0.analysis.indicators.body_fat.classification', 'Baixa')
            ->assertJsonPath('clients.0.assessments.0.height_cm_at_assessment', 160)
            ->assertJsonPath('clients.0.assessments.0.biological_sex_at_assessment', 'female');
    }

    public function test_hbf_514c_device_limits_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Altura Invalida',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'female',
            'height_cm' => 99.9,
        ])->assertJsonValidationErrors(['height_cm']);

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Limites Omron',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'female',
            'height_cm' => 165,
            'email' => 'limites@example.com',
        ]);

        $basePayload = [
            'bioimpedance_client_id' => $clientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 70,
            'scale_bmi' => 25.7,
            'body_fat_percentage' => 30,
            'skeletal_muscle_percentage' => 28,
            'resting_metabolism_kcal' => 1450,
            'body_age' => 40,
            'visceral_fat_level' => 9,
        ];

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            ...$basePayload,
            'visceral_fat_level' => 14.5,
        ])->assertJsonValidationErrors(['visceral_fat_level']);

        foreach ([
            'weight_kg' => 150.1,
            'scale_bmi' => 6.9,
            'body_fat_percentage' => 4.9,
            'skeletal_muscle_percentage' => 50.1,
            'resting_metabolism_kcal' => 4000,
            'body_age' => 81,
            'visceral_fat_level' => 31,
        ] as $field => $value) {
            $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
                ...$basePayload,
                $field => $value,
            ])->assertJsonValidationErrors([$field]);
        }
    }

    public function test_hbf_514c_device_limit_edges_are_accepted(): void
    {
        $user = User::factory()->create();

        $minimumClientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Limites Minimos',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'female',
            'height_cm' => 100,
            'email' => 'limites.minimos@example.com',
        ]);

        $minimumClientResponse->assertCreated();

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $minimumClientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 2,
            'scale_bmi' => 7,
            'body_fat_percentage' => 5,
            'skeletal_muscle_percentage' => 5,
            'resting_metabolism_kcal' => 385,
            'body_age' => 18,
            'visceral_fat_level' => 1,
        ])->assertCreated();

        $maximumClientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Limites Maximos',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'male',
            'height_cm' => 199.5,
            'email' => 'limites.maximos@example.com',
        ]);

        $maximumClientResponse->assertCreated();

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $maximumClientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 150,
            'scale_bmi' => 90,
            'body_fat_percentage' => 60,
            'skeletal_muscle_percentage' => 50,
            'resting_metabolism_kcal' => 3999,
            'body_age' => 80,
            'visceral_fat_level' => 30,
        ])->assertCreated();
    }

    public function test_assessment_date_cannot_be_future_or_before_birth_date(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Datas Invalidas',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'female',
            'height_cm' => 165,
            'email' => 'datas.invalidas@example.com',
        ]);

        $payload = [
            'bioimpedance_client_id' => $clientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 70,
            'scale_bmi' => 25.7,
            'body_fat_percentage' => 30,
            'skeletal_muscle_percentage' => 28,
            'resting_metabolism_kcal' => 1450,
            'body_age' => 40,
            'visceral_fat_level' => 9,
        ];

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            ...$payload,
            'evaluated_at' => now()->addDay()->toDateTimeString(),
        ])->assertJsonValidationErrors(['evaluated_at']);

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            ...$payload,
            'evaluated_at' => '1989-12-31 09:30:00',
        ])->assertJsonValidationErrors(['evaluated_at']);
    }

    public function test_hbf_514c_classification_boundaries_are_stable(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Fronteiras HBF',
            'birth_date' => '1981-07-03',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'email' => 'fronteiras@example.com',
        ]);

        $clientId = $clientResponse->json('client.id');

        $this->assertBodyFatClassification($user, $clientId, 21.9, 'Normal');
        $this->assertBodyFatClassification($user, $clientId, 22.0, 'Elevada');
        $this->assertBodyFatClassification($user, $clientId, 27.9, 'Elevada');
        $this->assertBodyFatClassification($user, $clientId, 28.0, 'Muito elevada');
        $this->assertVisceralFatClassification($user, $clientId, 9, 'Normal');
        $this->assertVisceralFatClassification($user, $clientId, 10, 'Elevada');
        $this->assertVisceralFatClassification($user, $clientId, 14, 'Elevada');
        $this->assertVisceralFatClassification($user, $clientId, 15, 'Muito elevada');
        $this->assertSkeletalMuscleClassification($user, $clientId, 33.0, 'Baixo');
        $this->assertSkeletalMuscleClassification($user, $clientId, 33.1, 'Normal');
        $this->assertSkeletalMuscleClassification($user, $clientId, 39.1, 'Normal');
        $this->assertSkeletalMuscleClassification($user, $clientId, 39.2, 'Alto');
        $this->assertSkeletalMuscleClassification($user, $clientId, 43.8, 'Alto');
        $this->assertSkeletalMuscleClassification($user, $clientId, 43.9, 'Muito alto');
    }

    public function test_hbf_514c_age_band_transitions_are_classified_by_evaluation_date(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Virada Faixa Etaria',
            'birth_date' => '1986-08-08',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'email' => 'virada.faixa@example.com',
        ]);

        $clientId = $clientResponse->json('client.id');

        $this->assertBodyFatClassification($user, $clientId, 20.0, 'Elevada', '2026-08-07 09:30:00');
        $this->assertBodyFatClassification($user, $clientId, 20.0, 'Normal', '2026-08-08 09:30:00');

        BioimpedanceClient::query()->find($clientId)->update([
            'birth_date' => '1966-08-08',
        ]);

        $this->assertBodyFatClassification($user, $clientId, 12.0, 'Normal', '2026-08-07 09:30:00');
        $this->assertBodyFatClassification($user, $clientId, 12.0, 'Baixa', '2026-08-08 09:30:00');
    }

    public function test_guest_cannot_access_bioimpedance_data(): void
    {
        $this->getJson(route('bioimpedance.index'))->assertUnauthorized();
    }

    private function assertBodyFatClassification(User $user, int $clientId, float $value, string $classification, string $evaluatedAt = '2026-08-07 09:30:00'): void
    {
        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientId,
            'evaluated_at' => $evaluatedAt,
            'weight_kg' => 95.2,
            'scale_bmi' => 31.4,
            'body_fat_percentage' => $value,
            'skeletal_muscle_percentage' => 37.6,
            'resting_metabolism_kcal' => 1935,
            'body_age' => 64,
            'visceral_fat_level' => 14,
        ])->assertCreated()
            ->assertJsonPath('assessment.analysis.indicators.body_fat.classification', $classification);
    }

    private function assertSkeletalMuscleClassification(User $user, int $clientId, float $value, string $classification): void
    {
        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientId,
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 95.2,
            'scale_bmi' => 31.4,
            'body_fat_percentage' => 20.5,
            'skeletal_muscle_percentage' => $value,
            'resting_metabolism_kcal' => 1935,
            'body_age' => 64,
            'visceral_fat_level' => 14,
        ])->assertCreated()
            ->assertJsonPath('assessment.analysis.indicators.skeletal_muscle.classification', $classification);
    }

    private function assertVisceralFatClassification(User $user, int $clientId, int $value, string $classification): void
    {
        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientId,
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 95.2,
            'scale_bmi' => 31.4,
            'body_fat_percentage' => 20.5,
            'skeletal_muscle_percentage' => 37.6,
            'resting_metabolism_kcal' => 1935,
            'body_age' => 64,
            'visceral_fat_level' => $value,
        ])->assertCreated()
            ->assertJsonPath('assessment.analysis.indicators.visceral_fat.classification', $classification);
    }
}
