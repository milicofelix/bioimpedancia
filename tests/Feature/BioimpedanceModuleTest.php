<?php

namespace Tests\Feature;

use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisRequest;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceAssessmentAudit;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Bioimpedance\BioimpedanceReportShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_clinic_settings_can_be_updated_for_reports(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)->patchJson(route('bioimpedance.clinic.update'), [
            'display_name' => 'Ricosty Emagrecimento e Estética',
            'legal_name' => 'Ricosty Clinica LTDA',
            'document' => '12.345.678/0001-99',
            'phone' => '(11) 3333-4444',
            'whatsapp' => '(11) 95555-4444',
            'email' => 'contato@ricosty.local',
            'address' => 'Rua da Clínica, 100',
            'instagram' => '@ricosty',
            'website' => 'https://ricosty.local',
            'primary_color' => '#cc7a8a',
            'secondary_color' => '#333333',
            'logo_url' => '/images/brand/ricosty-logo.png',
            'contact' => 'Emagrecimento e estética avançada',
            'footer_text' => 'Rodapé personalizado da clínica',
            'technical_notice' => 'Aviso técnico personalizado.',
        ])->assertOk()
            ->assertJsonPath('clinic.legal_name', 'Ricosty Clinica LTDA')
            ->assertJsonPath('clinic.primary_color', '#cc7a8a')
            ->assertJsonPath('clinic.footer_text', 'Rodapé personalizado da clínica');

        $this->actingAs($user)->getJson(route('bioimpedance.index'))
            ->assertOk()
            ->assertJsonPath('clinic.contact', 'Emagrecimento e estética avançada')
            ->assertJsonPath('clinic.technical_notice', 'Aviso técnico personalizado.');
    }

    public function test_only_admin_can_manage_clinic_settings_and_users(): void
    {
        $professional = User::factory()->create(['role' => User::ROLE_PROFESSIONAL]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($professional)->patchJson(route('bioimpedance.clinic.update'), [
            'display_name' => 'Bloqueada',
            'primary_color' => '#cc7a8a',
            'secondary_color' => '#333333',
        ])->assertForbidden();

        $this->actingAs($professional)->postJson(route('bioimpedance.users.store'), [
            'name' => 'Nova Pessoa',
            'email' => 'nova@example.com',
            'password' => 'password',
            'role' => User::ROLE_VIEWER,
        ])->assertForbidden();

        $this->actingAs($admin)->postJson(route('bioimpedance.users.store'), [
            'name' => 'Recepção Ricosty',
            'email' => 'recepcao@example.com',
            'password' => 'password',
            'role' => User::ROLE_RECEPTION,
        ])->assertCreated()
            ->assertJsonFragment(['email' => 'recepcao@example.com']);

        $createdUser = User::query()->where('email', 'recepcao@example.com')->first();
        $this->actingAs($admin)->patchJson(route('bioimpedance.users.inactivate', $createdUser->id))
            ->assertOk();

        $this->assertNotNull($createdUser->refresh()->inactivated_at);
    }

    public function test_viewer_user_can_read_but_cannot_change_records(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->getJson(route('bioimpedance.index'))
            ->assertOk();

        $this->actingAs($viewer)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Cliente Bloqueado',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'female',
            'height_cm' => 165,
        ])->assertForbidden();
    }

    public function test_admin_can_export_client_privacy_data_and_action_is_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $assessment = $this->createAssessmentForUser($admin);
        $client = $assessment->client;

        $response = $this->actingAs($admin)->get(route('bioimpedance.clients.privacy-export', $client->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename="dados-lgpd-cliente-avaliacao-'.now()->format('Y-m-d').'.json"');
        $this->assertSame('Cliente Avaliacao', $response->json('client.full_name'));
        $this->assertCount(1, $response->json('client.assessments'));

        $client->refresh();
        $this->assertNotNull($client->privacy_exported_at);
        $this->assertSame(1, $client->privacy_export_count);
        $this->assertDatabaseHas('app_audits', [
            'user_id' => $admin->id,
            'action' => 'bioimpedance_client.privacy_exported',
            'auditable_type' => $client::class,
            'auditable_id' => $client->id,
        ]);
    }

    public function test_only_admin_can_anonymize_client_and_identifier_data_is_removed(): void
    {
        $professional = User::factory()->create(['role' => User::ROLE_PROFESSIONAL]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $assessment = $this->createAssessmentForUser($admin);
        $client = $assessment->client;

        $this->actingAs($professional)->patchJson(route('bioimpedance.clients.anonymize', $client->id), [
            'anonymization_reason' => 'Solicitação formal do cliente.',
        ])->assertForbidden();

        $response = $this->actingAs($admin)->patchJson(route('bioimpedance.clients.anonymize', $client->id), [
            'anonymization_reason' => 'Solicitação formal do cliente.',
        ]);

        $response->assertOk()
            ->assertJsonPath('client.is_anonymized', true)
            ->assertJsonPath('client.email', null)
            ->assertJsonPath('client.phone', null)
            ->assertJsonPath('client.full_name', 'Cliente anonimizado #'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT))
            ->assertJsonCount(1, 'client.assessments');

        $client->refresh();
        $this->assertNotNull($client->anonymized_at);
        $this->assertSame($admin->id, $client->anonymized_by_user_id);

        $this->actingAs($admin)->putJson(route('bioimpedance.clients.update', $client->id), [
            'full_name' => 'Tentativa',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'female',
            'height_cm' => 165,
        ])->assertJsonValidationErrors(['bioimpedance_client_id']);
    }

    public function test_all_client_assessments_are_returned_for_history(): void
    {
        $user = User::factory()->create();

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Historico Completo',
            'birth_date' => '1990-01-01',
            'biological_sex' => 'female',
            'height_cm' => 165,
            'email' => 'historico.completo@example.com',
        ]);

        for ($month = 1; $month <= 6; $month++) {
            $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
                'bioimpedance_client_id' => $clientResponse->json('client.id'),
                'evaluated_at' => "2026-0{$month}-07 09:30:00",
                'weight_kg' => 70 + $month,
                'scale_bmi' => 25.7,
                'body_fat_percentage' => 30,
                'skeletal_muscle_percentage' => 28,
                'resting_metabolism_kcal' => 1450,
                'body_age' => 40,
                'visceral_fat_level' => 9,
            ])->assertCreated();
        }

        $this->actingAs($user)->getJson(route('bioimpedance.index'))
            ->assertOk()
            ->assertJsonCount(6, 'clients.0.assessments')
            ->assertJsonPath('clients.0.assessments.0.weight_kg', 76);
    }

    public function test_assessment_can_be_corrected_with_audit_trail(): void
    {
        $user = User::factory()->create();
        $assessment = $this->createAssessmentForUser($user);

        $response = $this->actingAs($user)->putJson(route('bioimpedance.assessments.update', $assessment->id), [
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 82.5,
            'scale_bmi' => 27.2,
            'body_fat_percentage' => 21.5,
            'skeletal_muscle_percentage' => 37.5,
            'resting_metabolism_kcal' => 1810,
            'body_age' => 41,
            'visceral_fat_level' => 10,
            'notes' => 'Peso corrigido por erro de digitação.',
            'change_reason' => 'Corrigir peso digitado incorretamente.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('assessment.weight_kg', 82.5);
        $response->assertJsonPath('assessment.correction_count', 1);
        $response->assertJsonPath('assessment.corrected_by_name', $user->name);
        $this->assertSame(1, BioimpedanceAssessmentAudit::query()->where('action', 'corrected')->count());
        $this->assertDatabaseHas('bioimpedance_assessment_audits', [
            'bioimpedance_assessment_id' => $assessment->id,
            'user_id' => $user->id,
            'action' => 'corrected',
            'reason' => 'Corrigir peso digitado incorretamente.',
        ]);
    }

    public function test_assessment_correction_requires_reason(): void
    {
        $user = User::factory()->create();
        $assessment = $this->createAssessmentForUser($user);

        $this->actingAs($user)->putJson(route('bioimpedance.assessments.update', $assessment->id), [
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 82.5,
            'scale_bmi' => 27.2,
            'body_fat_percentage' => 21.5,
            'skeletal_muscle_percentage' => 37.5,
            'resting_metabolism_kcal' => 1810,
            'body_age' => 41,
            'visceral_fat_level' => 10,
        ])->assertJsonValidationErrors(['change_reason']);
    }

    public function test_assessment_can_be_canceled_without_being_deleted(): void
    {
        $user = User::factory()->create();
        $assessment = $this->createAssessmentForUser($user);

        $this->actingAs($user)->patchJson(route('bioimpedance.assessments.cancel', $assessment->id), [
            'cancellation_reason' => 'Avaliação registrada no cliente errado.',
        ])->assertOk()
            ->assertJsonPath('assessment.is_canceled', true)
            ->assertJsonPath('assessment.cancellation_reason', 'Avaliação registrada no cliente errado.');

        $this->assertDatabaseHas('bioimpedance_assessments', [
            'id' => $assessment->id,
            'cancellation_reason' => 'Avaliação registrada no cliente errado.',
        ]);
        $this->assertSame(1, BioimpedanceAssessmentAudit::query()->where('action', 'canceled')->count());

        $this->actingAs($user)->putJson(route('bioimpedance.assessments.update', $assessment->id), [
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 82.5,
            'scale_bmi' => 27.2,
            'body_fat_percentage' => 21.5,
            'skeletal_muscle_percentage' => 37.5,
            'resting_metabolism_kcal' => 1810,
            'body_age' => 41,
            'visceral_fat_level' => 10,
            'change_reason' => 'Tentativa de corrigir cancelada.',
        ])->assertJsonValidationErrors(['bioimpedance_assessment_id']);
    }

    public function test_assessment_pdf_can_be_downloaded_and_marks_report_issue(): void
    {
        $user = User::factory()->create();
        $assessment = $this->createAssessmentForUser($user);

        $response = $this->actingAs($user)->get(route('bioimpedance.assessments.pdf', $assessment->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('bioimpedancia-cliente-avaliacao-2026-08-07.pdf', $response->headers->get('content-disposition'));

        $assessment->refresh();
        $this->assertNotNull($assessment->report_issued_at);
        $this->assertSame(1, $assessment->report_issue_count);
        $this->assertDatabaseHas('app_audits', [
            'user_id' => $user->id,
            'action' => 'bioimpedance_assessment.report_downloaded',
            'auditable_type' => $assessment::class,
            'auditable_id' => $assessment->id,
        ]);
    }

    public function test_assessment_report_can_be_shared_with_temporary_public_link(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PROFESSIONAL]);
        $assessment = $this->createAssessmentForUser($user);

        $response = $this->actingAs($user)->postJson(route('bioimpedance.assessments.shares.store', $assessment->id), [
            'channel' => 'whatsapp',
            'recipient' => '11999999999',
            'expires_in_days' => 7,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('share.channel', 'whatsapp');
        $this->assertStringContainsString('Sua avaliação de bioimpedância', $response->json('share.message'));
        $this->assertStringNotContainsString('80', $response->json('share.message'));
        $this->assertNotNull($response->json('share.url'));

        $publicResponse = $this->get($response->json('share.url'));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Relatório de bioimpedância');

        $shareId = $response->json('share.id');
        $this->assertDatabaseHas('bioimpedance_report_shares', [
            'id' => $shareId,
            'view_count' => 1,
        ]);
        $this->assertDatabaseHas('app_audits', [
            'action' => 'bioimpedance_report_share.viewed',
            'auditable_id' => $shareId,
        ]);
    }

    public function test_report_share_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $assessment = $this->createAssessmentForUser($user);

        $shareResponse = $this->actingAs($user)->postJson(route('bioimpedance.assessments.shares.store', $assessment->id), [
            'channel' => 'copy',
            'expires_in_days' => 7,
        ]);

        $this->actingAs($user)->patchJson(route('bioimpedance.report-shares.revoke', $shareResponse->json('share.id')))
            ->assertOk()
            ->assertJsonPath('share.is_active', false);

        $this->get($shareResponse->json('share.url'))->assertStatus(410);
    }

    public function test_legacy_pending_analysis_is_refreshed_before_pdf_download(): void
    {
        $user = User::factory()->create();
        $assessment = $this->createAssessmentForUser($user);
        $assessment->forceFill([
            'analysis' => [
                'source' => 'Omron HBF-514C',
                'summary' => 'IMC calculado em 31.4 kg/m2, classificado como Obesidade grau I. Gordura corporal registrada em 20,5%.',
                'indicators' => [
                    'body_fat' => ['classification' => 'Aguardando manual Omron', 'tone' => 'pending', 'pending' => true],
                    'skeletal_muscle' => ['classification' => 'Aguardando manual Omron', 'tone' => 'pending', 'pending' => true],
                    'visceral_fat' => ['classification' => 'Aguardando manual Omron', 'tone' => 'pending', 'pending' => true],
                ],
            ],
        ])->save();

        $this->actingAs($user)->get(route('bioimpedance.assessments.pdf', $assessment->id))
            ->assertOk();

        $assessment->refresh();
        $this->assertSame('Elevada', $assessment->analysis['indicators']['body_fat']['classification']);
        $this->assertSame('Normal', $assessment->analysis['indicators']['skeletal_muscle']['classification']);
        $this->assertSame('Normal', $assessment->analysis['indicators']['visceral_fat']['classification']);
        $this->assertSame('1.0.0', $assessment->analysis['reference']['classification_version']);
        $this->assertStringContainsString('kg/m²', $assessment->analysis['summary']);
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

    public function test_observation_assistant_suggests_and_professional_approves_notes_without_changing_classification(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_PROFESSIONAL,
        ]);

        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Adriano Freitas',
            'birth_date' => '1981-07-03',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'email' => 'assistente@example.com',
        ]);

        $clientId = $clientResponse->json('client.id');

        $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientId,
            'evaluated_at' => '2026-07-15 09:30:00',
            'weight_kg' => 97.2,
            'scale_bmi' => 32.1,
            'body_fat_percentage' => 22.5,
            'skeletal_muscle_percentage' => 37.0,
            'resting_metabolism_kcal' => 1940,
            'body_age' => 65,
            'visceral_fat_level' => 15,
        ])->assertCreated();

        $currentResponse = $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientId,
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 95.2,
            'scale_bmi' => 31.4,
            'body_fat_percentage' => 20.5,
            'skeletal_muscle_percentage' => 37.6,
            'resting_metabolism_kcal' => 1935,
            'body_age' => 64,
            'visceral_fat_level' => 14,
        ])->assertCreated();

        $assessmentId = $currentResponse->json('assessment.id');
        $originalAnalysis = BioimpedanceAssessment::query()->findOrFail($assessmentId)->analysis;

        $suggestionResponse = $this->actingAs($user)->getJson(route('bioimpedance.assessments.observation-suggestion', $assessmentId));

        $suggestionResponse->assertOk();
        $suggestionResponse->assertJsonPath('assistant.status', 'generated');
        $suggestionResponse->assertJsonPath('assistant.validation_status', 'passed');
        $suggestionResponse->assertJsonPath('assistant.context.age_at_assessment', 45);
        $suggestionResponse->assertJsonPath('assistant.context.device_model', 'HBF-514C');
        $suggestionResponse->assertJsonPath('assistant.notice', 'Sugestão RAG validada para revisão do profissional. As classificações oficiais não foram alteradas.');
        $this->assertStringContainsString('gordura corporal normal', $suggestionResponse->json('assistant.suggestion'));
        $this->assertStringContainsString('gordura visceral elevada', $suggestionResponse->json('assistant.suggestion'));
        $this->assertStringContainsString('19 anos acima', $suggestionResponse->json('assistant.suggestion'));
        $this->assertStringContainsString('redução de 2,0 kg em peso', $suggestionResponse->json('assistant.suggestion'));
        $this->assertContains('omron-hbf514c-visceral-fat', collect($suggestionResponse->json('assistant.sources'))->pluck('id'));
        $this->assertDatabaseHas('bioimpedance_ai_analysis_requests', [
            'id' => $suggestionResponse->json('assistant.request_id'),
            'bioimpedance_assessment_id' => $assessmentId,
            'status' => 'generated',
        ]);
        $this->assertDatabaseHas('bioimpedance_ai_analysis_outputs', [
            'id' => $suggestionResponse->json('assistant.output_id'),
            'validation_status' => 'passed',
        ]);

        $approvedNotes = $suggestionResponse->json('assistant.suggestion').' Orientação final revisada pelo profissional.';
        $approveResponse = $this->actingAs($user)->patchJson(route('bioimpedance.assessments.observation.approve', $assessmentId), [
            'notes' => $approvedNotes,
            'review_action' => 'edited',
            'assistant_output_id' => $suggestionResponse->json('assistant.output_id'),
        ]);

        $approveResponse->assertOk();
        $approveResponse->assertJsonPath('assessment.notes', $approvedNotes);
        $this->assertDatabaseHas('bioimpedance_assessment_audits', [
            'bioimpedance_assessment_id' => $assessmentId,
            'action' => 'observation_edited',
        ]);
        $this->assertNotNull(BioimpedanceAiAnalysisOutput::query()->find($suggestionResponse->json('assistant.output_id'))->approved_at);
        $this->assertSame(1, BioimpedanceAiAnalysisRequest::query()->where('bioimpedance_assessment_id', $assessmentId)->count());
        $this->assertSame($originalAnalysis, BioimpedanceAssessment::query()->findOrFail($assessmentId)->analysis);
    }

    public function test_admin_index_includes_operational_dashboard_metrics(): void
    {
        Carbon::setTestNow('2026-08-08 10:00:00');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $professional = User::factory()->create([
            'name' => 'Milico Felix',
            'role' => User::ROLE_PROFESSIONAL,
        ]);

        $newClient = $this->createClientRecord('Cliente Novo', 'novo@example.com');
        $returningClient = $this->createClientRecord('Cliente Recorrente', 'recorrente@example.com');
        $overdueClient = $this->createClientRecord('Cliente Sem Retorno', 'sem.retorno@example.com', '2026-08-01');
        $upcomingClient = $this->createClientRecord('Cliente Proxima Avaliacao', 'proxima@example.com', '2026-08-15');

        $returningClient->forceFill(['created_at' => '2026-07-01 10:00:00'])->save();
        $overdueClient->forceFill(['created_at' => '2026-07-01 10:00:00'])->save();
        $upcomingClient->forceFill(['created_at' => '2026-07-01 10:00:00'])->save();

        $this->createAssessmentRecord($returningClient, $professional, '2026-07-15 09:00:00');
        $monthlyAssessment = $this->createAssessmentRecord($returningClient, $professional, '2026-08-06 09:00:00');
        $this->createAssessmentRecord($newClient, $professional, '2026-08-07 09:00:00');
        $this->createAssessmentRecord($overdueClient, $professional, '2026-07-20 09:00:00');

        AppAudit::query()->create([
            'user_id' => $admin->id,
            'action' => 'bioimpedance_assessment.report_downloaded',
            'auditable_type' => BioimpedanceAssessment::class,
            'auditable_id' => $monthlyAssessment->id,
            'description' => 'Relatório PDF acessado',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        BioimpedanceReportShare::query()->create([
            'bioimpedance_assessment_id' => $monthlyAssessment->id,
            'created_by_user_id' => $admin->id,
            'token_hash' => hash('sha256', 'token-admin-dashboard'),
            'channel' => 'whatsapp',
            'recipient' => '(11) 90000-0000',
            'message' => 'Mensagem de compartilhamento',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($admin)->getJson(route('bioimpedance.index'));

        $response->assertOk();
        $response->assertJsonPath('admin_dashboard.cards.assessments_this_month', 2);
        $response->assertJsonPath('admin_dashboard.cards.clients_new_this_month', 1);
        $response->assertJsonPath('admin_dashboard.cards.returning_clients_this_month', 1);
        $response->assertJsonPath('admin_dashboard.cards.clients_without_return', 1);
        $response->assertJsonPath('admin_dashboard.cards.upcoming_reassessments', 1);
        $response->assertJsonPath('admin_dashboard.cards.evolutions_registered', 1);
        $response->assertJsonPath('admin_dashboard.cards.reports_issued_this_month', 1);
        $response->assertJsonPath('admin_dashboard.cards.shares_sent_this_month', 1);
        $response->assertJsonPath('admin_dashboard.professionals.0.name', 'Milico Felix');
        $response->assertJsonPath('admin_dashboard.professionals.0.assessments_count', 2);
        $response->assertJsonPath('admin_dashboard.clients_without_return.0.full_name', 'Cliente Sem Retorno');
        $response->assertJsonPath('admin_dashboard.upcoming_reassessments.0.full_name', 'Cliente Proxima Avaliacao');

        Carbon::setTestNow();
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

    private function createAssessmentForUser(User $user): BioimpedanceAssessment
    {
        $clientResponse = $this->actingAs($user)->postJson(route('bioimpedance.clients.store'), [
            'full_name' => 'Cliente Avaliacao',
            'birth_date' => '1988-01-01',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'email' => 'cliente.avaliacao.'.uniqid().'@example.com',
        ]);

        $assessmentResponse = $this->actingAs($user)->postJson(route('bioimpedance.assessments.store'), [
            'bioimpedance_client_id' => $clientResponse->json('client.id'),
            'evaluated_at' => '2026-08-07 09:30:00',
            'weight_kg' => 80,
            'scale_bmi' => 26.4,
            'body_fat_percentage' => 20,
            'skeletal_muscle_percentage' => 37,
            'resting_metabolism_kcal' => 1800,
            'body_age' => 40,
            'visceral_fat_level' => 9,
        ]);

        return BioimpedanceAssessment::query()->findOrFail($assessmentResponse->json('assessment.id'));
    }

    private function createClientRecord(string $name, string $email, ?string $nextAssessmentAt = null): BioimpedanceClient
    {
        return BioimpedanceClient::query()->create([
            'full_name' => $name,
            'birth_date' => '1988-01-01',
            'biological_sex' => 'male',
            'height_cm' => 174,
            'email' => $email,
            'next_assessment_at' => $nextAssessmentAt,
        ]);
    }

    private function createAssessmentRecord(BioimpedanceClient $client, User $professional, string $evaluatedAt): BioimpedanceAssessment
    {
        return BioimpedanceAssessment::query()->create([
            'bioimpedance_client_id' => $client->id,
            'user_id' => $professional->id,
            'age_at_assessment' => 38,
            'height_cm_at_assessment' => 174,
            'biological_sex_at_assessment' => 'male',
            'device_model' => 'HBF-514C',
            'reference_version' => '1.0.0',
            'evaluated_at' => $evaluatedAt,
            'weight_kg' => 80,
            'scale_bmi' => 26.4,
            'calculated_bmi' => 26.4,
            'bmi_difference' => 0,
            'body_fat_percentage' => 20,
            'skeletal_muscle_percentage' => 37,
            'resting_metabolism_kcal' => 1800,
            'body_age' => 40,
            'visceral_fat_level' => 9,
            'analysis' => [],
        ]);
    }
}
