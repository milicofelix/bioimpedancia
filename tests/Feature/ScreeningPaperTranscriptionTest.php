<?php

namespace Tests\Feature;

use App\Domain\Screening\CurrentAnxietyQuestionnaire;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Screening\ScreeningQuestionnaire;
use App\Models\Screening\ScreeningSession;
use App\Models\User;
use Database\Seeders\ScreeningQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningPaperTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_paper_application_has_no_public_invitation_and_can_be_resumed(): void
    {
        $session = $this->application();
        $this->assertNull($session->public_token_hash);
        $this->assertNull($session->expires_at);
        $this->assertNull($session->last_sent_at);
        $this->assertSame(0, $session->send_count);
        $this->get(route('screening.paper.show', $session))->assertOk()->assertSee('Registrar respostas do papel');
        $this->getJson(route('screening.invitations.index', $session->bioimpedance_client_id))
            ->assertOk()->assertJsonPath('sessions.0.transcription_url', route('screening.paper.show', $session));
    }

    public function test_transcription_finalizes_atomically_without_faking_digital_consent(): void
    {
        $session = $this->application();
        $data = $this->answers($session);
        $this->post(route('screening.paper.complete', $session), $data)->assertRedirect(route('screening.paper.show', $session));
        $session->refresh();
        $this->assertSame(ScreeningSession::STATUS_FINALIZED, $session->status);
        $this->assertNotNull($session->paper_consent_confirmed_at);
        $this->assertSame($session->requested_by_user_id, $session->transcribed_by_user_id);
        $this->assertNull($session->consent_accepted_at);
        $this->assertNull($session->identity_verified_at);
        $this->assertSame(1, $session->answers()->count());
        $this->postJson(route('screening.paper.complete', $session), $data)->assertUnprocessable();
        $this->assertSame(1, $session->answers()->count());
        $this->get(route('screening.paper.show', $session))->assertOk()->assertSee('Questionário finalizado');
    }

    public function test_invalid_answers_rollback_confirmation_and_status(): void
    {
        $session = $this->application();
        $data = $this->answers($session);
        $data['answers'] = [999999 => 999999];
        $this->postJson(route('screening.paper.complete', $session), $data)->assertUnprocessable();
        $this->assertSame(ScreeningSession::STATUS_PENDING, $session->fresh()->status);
        $this->assertNull($session->fresh()->paper_consent_confirmed_at);
        $this->assertSame(0, $session->answers()->count());
    }

    public function test_consent_cannot_be_skipped_and_other_professionals_cannot_transcribe(): void
    {
        $session = $this->application();
        $data = $this->answers($session);
        unset($data['paper_consent_confirmed']);
        $this->postJson(route('screening.paper.complete', $session), $data)->assertUnprocessable();
        $other = User::factory()->create(['role' => User::ROLE_PROFESSIONAL]);
        $this->actingAs($other)->get(route('screening.paper.show', $session))->assertForbidden();
        $this->postJson(route('screening.paper.complete', $session), $this->answers($session))->assertForbidden();
    }

    public function test_internal_application_cannot_be_converted_into_a_public_invitation(): void
    {
        $session = $this->application();
        $this->postJson(route('screening.invitations.resend', $session), ['expires_in_minutes' => 1440])->assertUnprocessable();
        $this->assertNull($session->fresh()->public_token_hash);
        $this->patchJson(route('screening.paper.cancel', $session), ['reason' => 'Documento incorreto; será criado outro lançamento.'])->assertOk();
        $this->assertSame(ScreeningSession::STATUS_CANCELLED, $session->fresh()->status);
        $this->get(route('screening.paper.show', $session))->assertStatus(410);
    }

    public function test_minor_requires_guardian_confirmation_from_paper(): void
    {
        $session = $this->application(minor: true);
        $this->assertTrue($session->is_minor_at_application);
        $data = $this->answers($session);
        $this->postJson(route('screening.paper.complete', $session), $data)->assertUnprocessable();
        $data['paper_guardian_consent_confirmed'] = true;
        $this->post(route('screening.paper.complete', $session), $data)->assertRedirect();
        $this->assertSame(ScreeningSession::STATUS_FINALIZED, $session->fresh()->status);
        $this->assertNull($session->fresh()->guardian_consent_accepted_at);
    }

    private function application(bool $minor = false): ScreeningSession
    {
        $user = User::factory()->create(['role' => User::ROLE_PROFESSIONAL]);
        $this->actingAs($user);
        config(['screening.minor_applications_enabled' => true]);
        $this->seed(ScreeningQuestionnaireSeeder::class);
        $questionnaire = ScreeningQuestionnaire::query()->where('code', CurrentAnxietyQuestionnaire::CODE)->firstOrFail();
        $questionnaire->update(['status' => ScreeningQuestionnaire::STATUS_ACTIVE, 'approved_at' => now(), 'professional_registration' => 'TEST-001', 'approved_by_user_id' => $user->id]);
        $client = BioimpedanceClient::query()->create([
            'full_name' => 'Cliente Teste', 'birth_date' => now()->subYears($minor ? 15 : 40)->toDateString(),
            'biological_sex' => 'female', 'height_cm' => 165,
        ]);
        $response = $this->postJson(route('screening.paper.store'), [
            'bioimpedance_client_id' => $client->id, 'screening_questionnaire_id' => $questionnaire->id,
            'paper_completed_on' => now()->toDateString(),
            'guardian_name' => $minor ? 'Responsável Teste' : null, 'guardian_relationship' => $minor ? 'Tutor' : null,
        ])->assertCreated();

        return ScreeningSession::query()->findOrFail($response->json('application.id'));
    }

    private function answers(ScreeningSession $session): array
    {
        return [
            'answers' => $session->questionnaire->questions->mapWithKeys(fn ($question) => [$question->id => $question->options->first()->id])->all(),
            'completion_confirmed' => true, 'paper_identity_confirmed' => true, 'paper_consent_confirmed' => true,
        ];
    }
}
