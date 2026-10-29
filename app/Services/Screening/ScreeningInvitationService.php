<?php

namespace App\Services\Screening;

use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Screening\ScreeningQuestionnaire;
use App\Models\Screening\ScreeningSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreeningInvitationService
{
    public const MAX_IDENTITY_ATTEMPTS = 5;

    public function create(Request $request, array $data): array
    {
        $requester = $this->authorizedRequester($request);
        $client = BioimpedanceClient::query()->findOrFail($data['bioimpedance_client_id']);
        $questionnaire = ScreeningQuestionnaire::query()->findOrFail($data['screening_questionnaire_id']);

        $this->ensureClientCanReceiveInvitation($client);
        $this->ensureQuestionnaireIsAvailable($questionnaire);
        $minorContext = $this->minorContext($client, $data);

        return DB::transaction(function () use ($request, $data, $requester, $client, $questionnaire, $minorContext): array {
            $plainToken = $this->newToken();
            $now = now();
            $session = ScreeningSession::query()->create([
                'bioimpedance_client_id' => $client->id,
                'screening_questionnaire_id' => $questionnaire->id,
                'requested_by_user_id' => $requester->id,
                'status' => ScreeningSession::STATUS_PENDING,
                'questionnaire_code' => $questionnaire->code,
                'questionnaire_version' => $questionnaire->version,
                'questionnaire_name' => $questionnaire->name,
                'requested_by_name' => $requester->name,
                'consent_text_snapshot' => $questionnaire->consent_text,
                'disclaimer_snapshot' => $questionnaire->disclaimer,
                'public_token_hash' => hash('sha256', $plainToken),
                'access_mode' => $data['access_mode'],
                'token_created_at' => $now,
                'expires_at' => $now->copy()->addMinutes((int) $data['expires_in_minutes']),
                'last_sent_at' => $now,
                'send_count' => 1,
                'is_minor_at_application' => $minorContext['is_minor'],
                'guardian_name' => $minorContext['guardian_name'],
                'guardian_relationship' => $minorContext['guardian_relationship'],
                'guardian_consent_text_snapshot' => $minorContext['consent_text'],
                'retention_expires_at' => $now->copy()->addDays(max(1, (int) config('screening.retention_days'))),
            ]);

            $this->audit($request, 'screening.invitation.created', $session, 'Convite de triagem criado', [
                'questionnaire_code' => $session->questionnaire_code,
                'questionnaire_version' => $session->questionnaire_version,
                'access_mode' => $session->access_mode,
                'expires_at' => $session->expires_at?->toIso8601String(),
                'minor_flow' => $session->is_minor_at_application,
            ]);

            return $this->invitationPayload($session, $plainToken);
        });
    }

    public function resend(Request $request, ScreeningSession $session, ?int $expiresInMinutes = null): array
    {
        $requester = $this->authorizedRequester($request);
        $this->ensureOwnedPendingSession($session, $requester);

        return DB::transaction(function () use ($request, $session, $expiresInMinutes): array {
            $plainToken = $this->newToken();
            $now = now();
            $session->update([
                'public_token_hash' => hash('sha256', $plainToken),
                'token_created_at' => $now,
                'expires_at' => $now->copy()->addMinutes($expiresInMinutes ?? 1440),
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'identity_verified_at' => null,
                'identity_attempts' => 0,
                'last_identity_attempt_at' => null,
                'last_sent_at' => $now,
                'send_count' => $session->send_count + 1,
            ]);

            $this->audit($request, 'screening.invitation.resent', $session, 'Convite de triagem reenviado com token substituído', [
                'expires_at' => $session->expires_at?->toIso8601String(),
                'send_count' => $session->send_count,
            ]);

            return $this->invitationPayload($session->refresh(), $plainToken);
        });
    }

    public function revoke(Request $request, ScreeningSession $session): array
    {
        $requester = $this->authorizedRequester($request);
        $this->ensureOwnedPendingSession($session, $requester);

        return DB::transaction(function () use ($request, $session, $requester): array {
            $session->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $requester->id,
                'public_token_hash' => null,
            ]);

            $this->audit($request, 'screening.invitation.revoked', $session, 'Acesso público da triagem revogado', []);

            return $this->sessionPayload($session->refresh());
        });
    }

    public function findPublicSession(string $plainToken): ScreeningSession
    {
        return ScreeningSession::query()
            ->with(['questionnaire.questions.options', 'client', 'answers'])
            ->where('public_token_hash', hash('sha256', $plainToken))
            ->firstOrFail();
    }

    public function recordAccess(ScreeningSession $session): void
    {
        $session->forceFill([
            'last_accessed_at' => now(),
            'access_count' => $session->access_count + 1,
        ])->save();
    }

    public function verifyIdentity(Request $request, ScreeningSession $session, array $data): void
    {
        if (! $session->publicAccessIsAvailable()) {
            throw ValidationException::withMessages(['identity_confirmation' => 'Este convite expirou, foi revogado ou já não está disponível.']);
        }

        $attemptRegistered = ScreeningSession::query()
            ->whereKey($session->id)
            ->where('status', ScreeningSession::STATUS_PENDING)
            ->where('identity_attempts', '<', self::MAX_IDENTITY_ATTEMPTS)
            ->update([
                'identity_attempts' => DB::raw('identity_attempts + 1'),
                'last_identity_attempt_at' => now(),
            ]);

        if ($attemptRegistered !== 1) {
            throw ValidationException::withMessages(['identity_confirmation' => 'O limite de tentativas foi atingido. Solicite um novo convite ao profissional.']);
        }

        $session->refresh()->loadMissing('client');

        if (! $this->identityMatches($session->client, $data['identity_confirmation'])) {
            throw ValidationException::withMessages(['identity_confirmation' => 'A informação não confere. Revise os dados ou solicite ajuda à clínica.']);
        }

        if ($session->is_minor_at_application) {
            if (! $this->guardianMatches($session, (string) ($data['guardian_name'] ?? ''))) {
                throw ValidationException::withMessages(['guardian_name' => 'O nome do responsável não confere com o convite.']);
            }

            if (! filter_var($data['guardian_consent'] ?? false, FILTER_VALIDATE_BOOL)) {
                throw ValidationException::withMessages(['guardian_consent' => 'O consentimento do responsável é obrigatório para continuar.']);
            }
        }

        DB::transaction(function () use ($request, $session): void {
            $verifiedAt = now();
            $started = ScreeningSession::query()
                ->whereKey($session->id)
                ->where('status', ScreeningSession::STATUS_PENDING)
                ->update([
                    'status' => ScreeningSession::STATUS_STARTED,
                    'identity_verified_at' => $verifiedAt,
                    'consent_accepted_at' => $verifiedAt,
                    'guardian_consent_accepted_at' => $session->is_minor_at_application ? $verifiedAt : null,
                    'started_at' => $verifiedAt,
                ]);

            if ($started !== 1) {
                throw ValidationException::withMessages(['identity_confirmation' => 'Este convite já foi iniciado em outro acesso.']);
            }

            $session->refresh();
            $this->audit($request, 'screening.invitation.identity_verified', $session, 'Identidade mínima e consentimento confirmados', [
                'access_mode' => $session->access_mode,
                'minor_flow' => $session->is_minor_at_application,
                'guardian_consent_confirmed' => (bool) $session->guardian_consent_accepted_at,
            ], null);
        });

        $request->session()->regenerate();
        $request->session()->put($this->verificationKey($session), $session->public_token_hash);
    }

    public function identityPrompt(ScreeningSession $session): array
    {
        if ($session->client->birth_date) {
            return ['type' => 'date', 'label' => 'Confirme sua data de nascimento', 'placeholder' => 'dd/mm/aaaa'];
        }

        return ['type' => 'tel', 'label' => 'Informe os 4 últimos números do seu telefone', 'placeholder' => '0000'];
    }

    public function isVerifiedInBrowser(Request $request, ScreeningSession $session): bool
    {
        return hash_equals((string) $session->public_token_hash, (string) $request->session()->get($this->verificationKey($session), ''));
    }

    public function canOpen(Request $request, ScreeningSession $session): bool
    {
        if ($session->anonymized_at || ! $session->bioimpedance_client_id || $session->revoked_at || ! $session->expires_at?->isFuture() || $session->status === ScreeningSession::STATUS_CANCELLED) {
            return false;
        }

        return $session->status === ScreeningSession::STATUS_PENDING
            || (in_array($session->status, [ScreeningSession::STATUS_STARTED, ScreeningSession::STATUS_FINALIZED], true)
                && $this->isVerifiedInBrowser($request, $session));
    }

    public function sessionPayload(ScreeningSession $session): array
    {
        return [
            'id' => $session->id,
            'questionnaire_code' => $session->questionnaire_code,
            'questionnaire_name' => $session->questionnaire_name,
            'questionnaire_version' => $session->questionnaire_version,
            'status' => $session->status,
            'access_mode' => $session->access_mode,
            'requested_by_name' => $session->requested_by_name,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'revoked_at' => $session->revoked_at?->toIso8601String(),
            'send_count' => $session->send_count,
            'is_available' => $session->publicAccessIsAvailable(),
            'created_at' => $session->created_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'finalized_at' => $session->finalized_at?->toIso8601String(),
            'total_score' => $session->total_score,
            'result_description' => $session->result_description,
            'document_authenticity_code' => $session->document_authenticity_code,
            'is_minor_at_application' => $session->is_minor_at_application,
            'retention_expires_at' => $session->retention_expires_at?->toIso8601String(),
            'comparison' => ['state' => 'unavailable', 'message' => 'A comparação ficará disponível após a conclusão.'],
        ];
    }

    private function invitationPayload(ScreeningSession $session, string $plainToken): array
    {
        $url = route('screening.public.show', ['token' => $plainToken]);

        return $this->sessionPayload($session) + [
            'url' => $url,
            'qr_payload' => $url,
        ];
    }

    private function authorizedRequester(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->isClinicalProfessional() && $user->isActive(), 403);

        return $user;
    }

    private function ensureClientCanReceiveInvitation(BioimpedanceClient $client): void
    {
        if ($client->inactivated_at || $client->anonymized_at) {
            throw ValidationException::withMessages(['bioimpedance_client_id' => 'O cliente está inativo ou anonimizado e não pode receber uma nova triagem.']);
        }

        if (! $client->birth_date && strlen((string) $client->phone_digits) < 4) {
            throw ValidationException::withMessages(['bioimpedance_client_id' => 'Cadastre a data de nascimento ou um telefone válido para confirmar a identidade do cliente.']);
        }
    }

    private function ensureQuestionnaireIsAvailable(ScreeningQuestionnaire $questionnaire): void
    {
        if (! $questionnaire->isAvailableForApplication()) {
            throw ValidationException::withMessages(['screening_questionnaire_id' => 'Este questionário ainda não foi aprovado e ativado por profissional habilitado.']);
        }
    }

    private function minorContext(BioimpedanceClient $client, array $data): array
    {
        $isMinor = $client->birth_date !== null && $client->birth_date->age < 18;

        if (! $isMinor) {
            return ['is_minor' => false, 'guardian_name' => null, 'guardian_relationship' => null, 'consent_text' => null];
        }

        if (! config('screening.minor_applications_enabled')) {
            throw ValidationException::withMessages(['bioimpedance_client_id' => 'Aplicações para menores estão desabilitadas nesta unidade.']);
        }

        $guardianName = trim((string) ($data['guardian_name'] ?? ''));
        $relationship = trim((string) ($data['guardian_relationship'] ?? ''));
        if ($guardianName === '' || $relationship === '') {
            throw ValidationException::withMessages([
                'guardian_name' => 'Informe o nome e o vínculo do responsável legal para criar a aplicação de um menor.',
            ]);
        }

        return [
            'is_minor' => true,
            'guardian_name' => $guardianName,
            'guardian_relationship' => $relationship,
            'consent_text' => 'Declaro ser responsável legal pelo menor, ter lido os limites deste questionário e consentir com o preenchimento e tratamento destes dados para triagem e acompanhamento profissional.',
        ];
    }

    private function ensureOwnedPendingSession(ScreeningSession $session, User $requester): void
    {
        abort_unless($requester->isAdmin() || $session->requested_by_user_id === $requester->id, 403);

        if ($session->status !== ScreeningSession::STATUS_PENDING) {
            throw ValidationException::withMessages(['session' => 'Somente aplicações pendentes podem ter o convite alterado.']);
        }
    }

    private function identityMatches(BioimpedanceClient $client, string $confirmation): bool
    {
        if ($client->birth_date) {
            $normalized = preg_replace('/\D+/', '', $confirmation);
            return hash_equals($client->birth_date->format('dmY'), (string) $normalized)
                || hash_equals($client->birth_date->format('Ymd'), (string) $normalized);
        }

        $phone = preg_replace('/\D+/', '', (string) $client->phone_digits);
        $provided = preg_replace('/\D+/', '', $confirmation);

        return strlen($provided) === 4 && hash_equals(substr($phone, -4), $provided);
    }

    private function guardianMatches(ScreeningSession $session, string $providedName): bool
    {
        $normalize = static fn (string $value): string => Str::lower((string) preg_replace('/\s+/u', ' ', trim($value)));
        $expected = $normalize((string) $session->guardian_name);
        $provided = $normalize($providedName);

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    private function newToken(): string
    {
        return Str::random(64);
    }

    private function verificationKey(ScreeningSession $session): string
    {
        return "screening_public_access.{$session->id}";
    }

    private function audit(Request $request, string $action, ScreeningSession $session, string $description, array $newValues, ?int $userId = -1): void
    {
        AppAudit::query()->create([
            'user_id' => $userId === -1 ? $request->user()?->id : $userId,
            'action' => $action,
            'auditable_type' => ScreeningSession::class,
            'auditable_id' => $session->id,
            'description' => $description,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }
}
