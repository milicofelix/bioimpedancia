<?php

namespace App\Services\Screening;

use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Screening\ScreeningQuestionnaire;
use App\Models\Screening\ScreeningSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreeningPaperTranscriptionService
{
    public function __construct(
        private readonly ScreeningSessionAccessAuthorizer $access,
        private readonly ScreeningSessionFinalizer $finalizer,
    ) {}

    public function create(Request $request, array $data): ScreeningSession
    {
        abort_unless($request->user()?->isClinicalProfessional() && $request->user()->isActive(), 403);

        return DB::transaction(function () use ($request, $data): ScreeningSession {
            $client = BioimpedanceClient::query()->lockForUpdate()->findOrFail($data['bioimpedance_client_id']);
            $this->ensureActiveClient($client);
            $questionnaire = ScreeningQuestionnaire::query()->lockForUpdate()->findOrFail($data['screening_questionnaire_id']);
            if (! $questionnaire->isAvailableForApplication()) {
                throw ValidationException::withMessages(['screening_questionnaire_id' => 'Selecione uma versão aprovada e ativa que corresponda ao formulário em papel.']);
            }
            $paperDate = Carbon::parse($data['paper_completed_on'])->startOfDay();
            if (! $client->birth_date || $paperDate->lt($client->birth_date->copy()->startOfDay())) {
                throw ValidationException::withMessages(['paper_completed_on' => 'Confira a data do formulário e cadastre a data de nascimento do cliente.']);
            }
            $minor = $client->birth_date->copy()->addYears(18)->startOfDay()->gt($paperDate);
            if ($minor && (! config('screening.minor_applications_enabled') || blank($data['guardian_name'] ?? null) || blank($data['guardian_relationship'] ?? null))) {
                throw ValidationException::withMessages(['guardian_name' => 'Para menores, habilite o fluxo da unidade e informe o nome e o vínculo do responsável legal.']);
            }
            $session = ScreeningSession::query()->create([
                'bioimpedance_client_id' => $client->id,
                'screening_questionnaire_id' => $questionnaire->id,
                'requested_by_user_id' => $request->user()->id,
                'requested_by_name' => $request->user()->name,
                'status' => ScreeningSession::STATUS_PENDING,
                'access_mode' => ScreeningSession::ACCESS_PAPER_TRANSCRIPTION,
                'paper_completed_on' => $paperDate,
                'questionnaire_code' => $questionnaire->code,
                'questionnaire_version' => $questionnaire->version,
                'questionnaire_name' => $questionnaire->name,
                'consent_text_snapshot' => $questionnaire->consent_text,
                'disclaimer_snapshot' => $questionnaire->disclaimer,
                'is_minor_at_application' => $minor,
                'guardian_name' => $minor ? trim($data['guardian_name']) : null,
                'guardian_relationship' => $minor ? trim($data['guardian_relationship']) : null,
                'public_token_hash' => null,
                'expires_at' => null,
                'send_count' => 0,
                'retention_expires_at' => now()->addDays(max(1, (int) config('screening.retention_days'))),
            ]);
            $this->audit($request, $session, 'created', 'Aplicação interna criada para transcrição de formulário em papel');

            return $session;
        });
    }

    public function authorize(Request $request, ScreeningSession $session): void
    {
        $this->access->authorize($request, $session);
        abort_unless($session->access_mode === ScreeningSession::ACCESS_PAPER_TRANSCRIPTION, 404);
        abort_if($session->revoked_at || $session->status === ScreeningSession::STATUS_CANCELLED, 410, 'Este lançamento foi cancelado.');
        if (! $session->statusIsTerminal()) {
            $this->ensureActiveClient($session->client);
            abort_if(! $session->retention_expires_at?->isFuture(), 410, 'O prazo de retenção deste lançamento terminou.');
        }
    }

    public function complete(Request $request, ScreeningSession $session, array $data): ScreeningSession
    {
        return DB::transaction(function () use ($request, $session, $data): ScreeningSession {
            $locked = ScreeningSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->authorize($request, $locked);
            if ($locked->status !== ScreeningSession::STATUS_PENDING) {
                throw ValidationException::withMessages(['session' => 'Este lançamento já foi concluído ou está indisponível. Consulte o histórico.']);
            }
            $confirmations = ['completion_confirmed', 'paper_identity_confirmed', 'paper_consent_confirmed'];
            if ($locked->is_minor_at_application) {
                $confirmations[] = 'paper_guardian_consent_confirmed';
            }
            foreach ($confirmations as $field) {
                if (! filter_var($data[$field] ?? false, FILTER_VALIDATE_BOOL)) {
                    throw ValidationException::withMessages([$field => 'Confira as respostas, a identificação e os consentimentos no documento original antes de finalizar.']);
                }
            }
            $locked->update([
                'status' => ScreeningSession::STATUS_STARTED,
                'started_at' => now(),
                'transcribed_by_user_id' => $request->user()->id,
                'paper_consent_confirmed_at' => now(),
            ]);
            $answers = collect($data['answers'])->map(fn ($optionId, $questionId) => [
                'question_id' => (int) $questionId, 'option_id' => (int) $optionId,
            ])->values()->all();
            $finalized = $this->finalizer->finalize($request, $locked, $answers);
            $this->audit($request, $finalized, 'completed', 'Respostas transcritas e conferidas com documento em papel; consentimento físico confirmado');

            return $finalized;
        });
    }

    public function cancel(Request $request, ScreeningSession $session, string $reason): ScreeningSession
    {
        return DB::transaction(function () use ($request, $session, $reason): ScreeningSession {
            $locked = ScreeningSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->authorize($request, $locked);
            abort_unless($locked->status === ScreeningSession::STATUS_PENDING, 422, 'Somente lançamentos pendentes podem ser cancelados.');
            $locked->update(['status' => ScreeningSession::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by_user_id' => $request->user()->id, 'cancellation_reason' => $reason]);
            $this->audit($request, $locked, 'cancelled', 'Lançamento de formulário em papel cancelado; registro preservado');

            return $locked;
        });
    }

    private function ensureActiveClient(BioimpedanceClient $client): void
    {
        if ($client->inactivated_at || $client->anonymized_at) {
            throw ValidationException::withMessages(['bioimpedance_client_id' => 'O cliente está inativo ou anonimizado; novos lançamentos não estão disponíveis.']);
        }
    }

    private function audit(Request $request, ScreeningSession $session, string $action, string $description): void
    {
        AppAudit::query()->create([
            'user_id' => $request->user()->id, 'action' => 'screening.paper.'.$action,
            'auditable_type' => ScreeningSession::class, 'auditable_id' => $session->id,
            'description' => $description,
            'new_values' => ['access_mode' => $session->access_mode, 'questionnaire_version' => $session->questionnaire_version, 'paper_completed_on' => $session->paper_completed_on?->toDateString(), 'response_content_logged' => false,
                'guardian_consent_physically_confirmed' => $action === 'completed' && $session->is_minor_at_application],
            'ip_address' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }
}
