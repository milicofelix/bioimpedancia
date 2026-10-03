<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceAssessmentAudit;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use App\Models\Bioimpedance\BioimpedanceReportShare;
use App\Models\User;
use App\Services\Bioimpedance\BioimpedanceAnalyzer;
use App\Services\Bioimpedance\BioimpedanceObservationAssistant;
use App\Services\Bioimpedance\BioimpedanceReportSharePresenter;
use App\Services\Bioimpedance\LegacyBioimpedanceAnalysisRefresher;
use App\Services\Bioimpedance\RelaxmedicImageProcessor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BioimpedanceController extends Controller
{
    public function __construct(private readonly BioimpedanceReportSharePresenter $reportSharePresenter) {}

    public function index(): JsonResponse
    {
        $clients = BioimpedanceClient::query()
            ->with(['assessments' => fn ($query) => $query->with('shares')->latest('evaluated_at')])
            ->orderBy('full_name')
            ->get()
            ->map(fn (BioimpedanceClient $client) => $this->clientPayload($client));

        return response()->json([
            'clients' => $clients,
            'clinic' => $this->clinicPayload(),
            'current_user' => $this->userPayload(request()->user()),
            'users' => request()->user()->isAdmin() ? $this->usersPayload() : [],
            'audit_events' => request()->user()->isAdmin() ? $this->auditEventsPayload() : [],
            'admin_dashboard' => request()->user()->isAdmin() ? $this->adminDashboardPayload() : null,
        ]);
    }

    public function adminDashboard(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'admin_dashboard' => $this->adminDashboardPayload(),
        ]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $this->authorizeCreateClinicalRecords($request);
        $validated = $this->validateClient($request);

        $client = BioimpedanceClient::query()->create($validated);
        $this->audit($request, 'bioimpedance_client.created', $client, 'Cliente cadastrado', null, $this->clientAuditPayload($client));

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($client)),
        ], 201);
    }

    public function updateClient(Request $request, BioimpedanceClient $client): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        $this->ensureClientIsNotAnonymized($client);
        $validated = $this->validateClient($request, $client);
        $oldValues = $this->clientAuditPayload($client);

        $client->update($validated);
        $this->audit($request, 'bioimpedance_client.updated', $client, 'Cliente atualizado', $oldValues, $this->clientAuditPayload($client->refresh()));

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($client->refresh())),
        ]);
    }

    public function inactivateClient(Request $request, BioimpedanceClient $client): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        $oldValues = $this->clientAuditPayload($client);
        $client->update([
            'inactivated_at' => now(),
        ]);
        $this->audit($request, 'bioimpedance_client.inactivated', $client, 'Cliente inativado', $oldValues, $this->clientAuditPayload($client->refresh()));

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($client->refresh())),
        ]);
    }

    public function exportClientPrivacyData(Request $request, BioimpedanceClient $client): Response
    {
        $this->authorizeAdmin($request);
        $this->loadClientAssessments($client);
        $exportCount = $client->privacy_export_count + 1;
        $client->update([
            'privacy_exported_at' => now(),
            'privacy_export_count' => $exportCount,
        ]);
        $this->audit($request, 'bioimpedance_client.privacy_exported', $client, 'Dados do cliente exportados para atendimento LGPD', null, [
            'client_id' => $client->id,
            'privacy_export_count' => $exportCount,
        ]);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'exported_by' => $this->userPayload($request->user()),
            'client' => $this->clientPayload($this->loadClientAssessments($client->refresh())),
            'clinic' => $this->clinicPayload(),
            'purpose' => 'Exportação de dados pessoais e avaliações para atendimento de solicitação LGPD.',
        ];

        return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->privacyExportFileName($client).'"',
        ]);
    }

    public function anonymizeClient(Request $request, BioimpedanceClient $client): JsonResponse
    {
        $this->authorizeAdmin($request);
        if ($client->anonymized_at) {
            return response()->json([
                'client' => $this->clientPayload($this->loadClientAssessments($client)),
                'audit_events' => $this->auditEventsPayload(),
            ]);
        }

        $request->validate([
            'anonymization_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $anonymizedName = 'Cliente anonimizado #'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT);

        $client->update([
            'full_name' => $anonymizedName,
            'birth_date' => '1900-01-01',
            'biological_sex' => 'female',
            'height_cm' => 100,
            'phone' => null,
            'phone_digits' => null,
            'email' => null,
            'cpf' => null,
            'address' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'consent_accepted_at' => null,
            'next_assessment_at' => null,
            'inactivated_at' => now(),
            'anonymized_at' => now(),
            'anonymized_by_user_id' => $request->user()->id,
            'notes' => 'Registro anonimizado por solicitação LGPD.',
        ]);

        $this->revokeClientPublicShares($client);
        $this->scrubClientAuditPersonalData($client);
        $this->audit($request, 'bioimpedance_client.anonymized', $client, 'Cliente anonimizado para atendimento LGPD', null, [
            'client_id' => $client->id,
            'anonymized_at' => $client->fresh()->anonymized_at?->toIso8601String(),
            'shares_revoked' => true,
            'reason_registered' => true,
        ]);

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($client)),
            'audit_events' => $this->auditEventsPayload(),
        ]);
    }

    public function updateClinicSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'document' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:160'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:180'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'technical_notice' => ['nullable', 'string', 'max:1000'],
            'scale_model' => ['required', Rule::in(BioimpedanceClinicSetting::SCALE_MODELS)],
        ]);

        $settings = BioimpedanceClinicSetting::current();
        $oldValues = $settings->payload();
        $settings->update($validated);
        $this->audit($request, 'clinic_settings.updated', $settings, 'Configurações da clínica atualizadas', $oldValues, $settings->refresh()->payload());

        return response()->json([
            'clinic' => $this->clinicPayload($settings->refresh()),
            'audit_events' => $this->auditEventsPayload(),
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user = User::query()->create($this->validateUser($request, requirePassword: true));
        $this->audit($request, 'user.created', $user, 'Usuário cadastrado', null, $this->userPayload($user));

        return response()->json([
            'users' => $this->usersPayload(),
            'audit_events' => $this->auditEventsPayload(),
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $this->validateUser($request, $user);
        if (($validated['role'] ?? null) !== User::ROLE_ADMIN && $user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'role' => 'Você não pode remover seu próprio perfil de administrador.',
            ]);
        }

        $oldValues = $this->userPayload($user);
        $user->update($validated);
        $this->audit($request, 'user.updated', $user, 'Usuário atualizado', $oldValues, $this->userPayload($user->refresh()));

        return response()->json([
            'users' => $this->usersPayload(),
            'audit_events' => $this->auditEventsPayload(),
        ]);
    }

    public function inactivateUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user_id' => 'Você não pode inativar seu próprio usuário.',
            ]);
        }

        $oldValues = $this->userPayload($user);
        $user->update(['inactivated_at' => now()]);
        $this->audit($request, 'user.inactivated', $user, 'Usuário inativado', $oldValues, $this->userPayload($user->refresh()));

        return response()->json([
            'users' => $this->usersPayload(),
            'audit_events' => $this->auditEventsPayload(),
        ]);
    }

    public function storeAssessment(Request $request, BioimpedanceAnalyzer $analyzer, RelaxmedicImageProcessor $imageProcessor): JsonResponse
    {
        $this->authorizeCreateClinicalRecords($request);
        $sourceMetadata = null;

        if (BioimpedanceClinicSetting::current()->scale_model === BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC) {
            $this->authorizeClinicalProfessional($request);
            $source = $request->validate([
                'relaxmedic_review_confirmed' => ['accepted'],
                'source_metadata' => ['required', 'array'],
                'source_metadata.name' => ['required', 'string', 'max:255'],
                'source_metadata.size_bytes' => ['required', 'integer', 'between:1,10485760'],
                'source_metadata.mime_type' => ['required', Rule::in(['image/jpeg'])],
                'source_metadata.stored' => ['required', 'declined'],
                'source_metadata.sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
                'source_metadata.processed_at' => ['required', 'date'],
                'source_metadata.processor_model' => ['required', 'string', 'max:100'],
                'source_metadata.signature' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            ], [
                'relaxmedic_review_confirmed.accepted' => 'Confirme que os dados foram conferidos com a imagem antes de salvar.',
                'source_metadata.required' => 'Processe novamente a imagem antes de salvar a avaliação.',
            ]);

            if (! $imageProcessor->verifyMetadata($source['source_metadata'])) {
                throw ValidationException::withMessages([
                    'source_metadata' => 'A origem da extração não pôde ser validada. Processe novamente a imagem.',
                ]);
            }

            $sourceMetadata = [
                'type' => 'relaxmedic_image_extraction',
                'image_name' => $source['source_metadata']['name'],
                'image_size_bytes' => $source['source_metadata']['size_bytes'],
                'image_mime_type' => $source['source_metadata']['mime_type'],
                'image_sha256' => $source['source_metadata']['sha256'],
                'image_stored' => false,
                'processed_at' => $source['source_metadata']['processed_at'],
                'processor_model' => $source['source_metadata']['processor_model'],
                'review_confirmed_at' => now()->toIso8601String(),
                'reviewed_by_user_id' => $request->user()->id,
            ];
        }

        $validated = $this->validateAssessment($request);

        $client = BioimpedanceClient::query()->findOrFail($validated['bioimpedance_client_id']);
        if ($client->inactivated_at) {
            throw ValidationException::withMessages([
                'bioimpedance_client_id' => 'Cliente inativo não pode receber novas avaliações.',
            ]);
        }
        $this->ensureClientIsNotAnonymized($client);

        $this->validateEvaluationDateAgainstBirthDate($client, $validated['evaluated_at']);

        $snapshot = $this->assessmentSnapshot($client, $validated['evaluated_at']);
        $analysis = $analyzer->analyze($client->toArray(), [...$validated, ...$snapshot]);

        $assessment = DB::transaction(function () use ($validated, $snapshot, $request, $analysis, $sourceMetadata): BioimpedanceAssessment {
            $assessment = BioimpedanceAssessment::query()->create([
                ...$validated,
                ...$snapshot,
                'user_id' => $request->user()->id,
                'calculated_bmi' => $analysis['calculated_bmi'],
                'bmi_difference' => $analysis['bmi_difference'],
                'analysis' => $analysis,
                'source_metadata' => $sourceMetadata,
            ]);

            $this->auditAssessment(
                $assessment,
                $request,
                'created',
                'Avaliação criada',
                [],
                $this->assessmentAuditValues($assessment),
            );
            $this->audit(
                $request,
                'bioimpedance_assessment.created',
                $assessment,
                'Avaliação de bioimpedância criada',
                null,
                $this->assessmentAuditValues($assessment),
            );

            return $assessment;
        });

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($client->refresh())),
            'assessment' => $this->assessmentPayload($assessment),
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ], 201);
    }

    public function processRelaxmedicImage(Request $request, RelaxmedicImageProcessor $processor): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);

        if (BioimpedanceClinicSetting::current()->scale_model !== BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC) {
            throw ValidationException::withMessages([
                'image' => 'Selecione a balança Relaxmedic nas configurações antes de processar a imagem.',
            ]);
        }

        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg',
                'extensions:jpg,jpeg',
                'mimetypes:image/jpeg',
                'max:10240',
                'dimensions:min_width=200,min_height=400,max_width=10000,max_height=20000',
            ],
        ], [
            'image.required' => 'Selecione uma imagem JPEG ou JPG.',
            'image.image' => 'O arquivo enviado não é uma imagem válida.',
            'image.mimes' => 'A imagem deve estar no formato JPEG ou JPG.',
            'image.extensions' => 'A extensão do arquivo deve ser JPEG ou JPG.',
            'image.mimetypes' => 'A imagem deve estar no formato JPEG ou JPG.',
            'image.max' => 'A imagem deve ter no máximo 10 MB.',
            'image.dimensions' => 'A imagem possui dimensões incompatíveis com o relatório.',
        ]);

        try {
            $extraction = $processor->process($validated['image']);
        } catch (ConnectionException|RequestException $exception) {
            return response()->json([
                'message' => 'Não foi possível acessar o serviço de leitura da imagem. Tente novamente.',
            ], 502);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['image' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'extraction' => $extraction,
        ]);
    }

    public function updateAssessment(Request $request, BioimpedanceAssessment $assessment, BioimpedanceAnalyzer $analyzer): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        if ($assessment->canceled_at) {
            throw ValidationException::withMessages([
                'bioimpedance_assessment_id' => 'Avaliação cancelada não pode ser alterada.',
            ]);
        }

        $validated = $this->validateAssessment($request, requireClient: false, deviceModel: $assessment->device_model);
        unset($validated['bioimpedance_client_id']);

        $changeReason = $request->validate([
            'change_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ])['change_reason'];

        $client = $assessment->client;
        $this->validateEvaluationDateAgainstBirthDate($client, $validated['evaluated_at']);

        $snapshot = $this->assessmentSnapshotForCorrection($assessment, $client, $validated['evaluated_at']);
        $analysis = $analyzer->analyze($client->toArray(), [...$validated, ...$snapshot]);
        $oldValues = $this->assessmentAuditValues($assessment);

        DB::transaction(function () use ($assessment, $validated, $snapshot, $request, $analysis, $changeReason, $oldValues): void {
            $assessment->update([
                ...$validated,
                ...$snapshot,
                'corrected_by_user_id' => $request->user()->id,
                'correction_count' => $assessment->correction_count + 1,
                'calculated_bmi' => $analysis['calculated_bmi'],
                'bmi_difference' => $analysis['bmi_difference'],
                'analysis' => $analysis,
            ]);

            $this->auditAssessment($assessment->refresh(), $request, 'corrected', $changeReason, $oldValues, $this->assessmentAuditValues($assessment));
            $this->audit(
                $request,
                'bioimpedance_assessment.corrected',
                $assessment,
                'Avaliação de bioimpedância corrigida: '.$changeReason,
                $oldValues,
                $this->assessmentAuditValues($assessment),
            );
        });

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($client->refresh())),
            'assessment' => $this->assessmentPayload($assessment),
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ]);
    }

    public function cancelAssessment(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        if ($assessment->canceled_at) {
            return response()->json([
                'client' => $this->clientPayload($this->loadClientAssessments($assessment->client)),
                'assessment' => $this->assessmentPayload($assessment),
            ]);
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $oldValues = $this->assessmentAuditValues($assessment);

        $assessment->update([
            'canceled_at' => now(),
            'canceled_by_user_id' => $request->user()->id,
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        $this->auditAssessment($assessment->refresh(), $request, 'canceled', $validated['cancellation_reason'], $oldValues, $this->assessmentAuditValues($assessment));

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($assessment->client->refresh())),
            'assessment' => $this->assessmentPayload($assessment),
        ]);
    }

    public function downloadAssessmentPdf(BioimpedanceAssessment $assessment, LegacyBioimpedanceAnalysisRefresher $legacyAnalysisRefresher): Response
    {
        $assessment->load(['client', 'professional', 'correctedBy', 'canceledBy']);
        $legacyAnalysisRefresher->refreshIfLegacy($assessment);
        $assessment->refresh()->load(['client', 'professional', 'correctedBy', 'canceledBy']);
        $assessment->update([
            'report_issued_at' => now(),
            'report_issue_count' => $assessment->report_issue_count + 1,
        ]);
        $assessment->refresh()->load(['client', 'professional', 'correctedBy', 'canceledBy']);
        config(['dompdf.public_path' => public_path()]);
        $this->audit(request(), 'bioimpedance_assessment.report_downloaded', $assessment, 'Relatório PDF acessado', null, [
            'assessment_id' => $assessment->id,
            'client_id' => $assessment->bioimpedance_client_id,
            'report_issue_count' => $assessment->report_issue_count,
        ]);

        $pdf = Pdf::loadView('bioimpedance.report-pdf', [
            'clinic' => [
                ...$this->clinicPayload(),
                'logo_data_uri' => $this->logoDataUri(),
            ],
            'client' => $this->clientPayload($assessment->client->setRelation('assessments', collect([$assessment]))),
            'assessment' => $this->assessmentPayload($assessment),
            'issuedAt' => $assessment->report_issued_at,
        ])->setPaper('a4');

        return $pdf->download($this->pdfFileName($assessment));
    }

    public function createAssessmentShare(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        $this->authorizeWrite($request);
        $assessment->load('client');
        if ($assessment->canceled_at) {
            throw ValidationException::withMessages([
                'bioimpedance_assessment_id' => 'Avaliação cancelada não pode ser compartilhada.',
            ]);
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'copy'])],
            'recipient' => ['nullable', 'string', 'max:160'],
            'expires_in_days' => ['required', 'integer', 'between:1,30'],
        ]);

        $plainToken = Str::random(48);
        $expiresAt = now()->addDays((int) $validated['expires_in_days']);
        $message = $this->reportSharePresenter->message($assessment);
        $share = BioimpedanceReportShare::query()->create([
            'bioimpedance_assessment_id' => $assessment->id,
            'created_by_user_id' => $request->user()->id,
            'token_hash' => hash('sha256', $plainToken),
            'channel' => $validated['channel'],
            'recipient' => $validated['recipient'] ?? null,
            'message' => $message,
            'expires_at' => $expiresAt,
        ]);

        $this->audit($request, 'bioimpedance_report_share.created', $share, 'Link temporário do relatório gerado', null, $this->reportSharePresenter->payload($share));

        return response()->json([
            'share' => $this->reportSharePresenter->payload($share, $plainToken),
            'assessment' => $this->assessmentPayload($assessment->refresh()->load('shares')),
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ], 201);
    }

    public function suggestAssessmentObservation(Request $request, BioimpedanceAssessment $assessment, BioimpedanceObservationAssistant $assistant): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        $assessment->load(['client', 'professional']);
        abort_if($assessment->canceled_at, 422, 'Avaliação cancelada não pode receber sugestão de observação.');

        return response()->json([
            'assistant' => $assistant->suggest($assessment, $request->user()),
        ]);
    }

    public function approveAssessmentObservation(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        if ($assessment->canceled_at) {
            throw ValidationException::withMessages([
                'bioimpedance_assessment_id' => 'Avaliação cancelada não pode receber observação oficial.',
            ]);
        }

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:20', 'max:2000'],
            'review_action' => ['required', Rule::in(['approved', 'edited'])],
            'assistant_output_id' => ['nullable', 'exists:bioimpedance_ai_analysis_outputs,id'],
        ]);

        $oldValues = $this->assessmentAuditValues($assessment);
        $assessment->update([
            'notes' => $validated['notes'],
        ]);

        $this->auditAssessment(
            $assessment->refresh(),
            $request,
            'observation_'.$validated['review_action'],
            $validated['review_action'] === 'edited'
                ? 'Sugestão do assistente editada e aprovada pelo profissional.'
                : 'Sugestão do assistente aprovada pelo profissional.',
            $oldValues,
            $this->assessmentAuditValues($assessment)
        );

        if ($validated['assistant_output_id'] ?? null) {
            BioimpedanceAiAnalysisOutput::query()
                ->where('id', $validated['assistant_output_id'])
                ->whereHas('request', fn ($query) => $query->where('bioimpedance_assessment_id', $assessment->id))
                ->update([
                    'professional_observation' => $validated['notes'],
                    'approved_by_user_id' => $request->user()->id,
                    'approved_at' => now(),
                ]);
        }

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($assessment->client->refresh())),
            'assessment' => $this->assessmentPayload($assessment),
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ]);
    }

    public function revokeAssessmentShare(Request $request, BioimpedanceReportShare $share): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        $oldValues = $this->reportSharePresenter->payload($share);
        $share->update(['revoked_at' => now()]);
        $this->audit($request, 'bioimpedance_report_share.revoked', $share, 'Link temporário do relatório revogado', $oldValues, $this->reportSharePresenter->payload($share->refresh()));

        return response()->json([
            'share' => $this->reportSharePresenter->payload($share),
            'assessment' => $this->assessmentPayload($share->assessment->refresh()->load('shares')),
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ]);
    }

    public function publicReport(string $token): Response
    {
        $share = BioimpedanceReportShare::query()
            ->where('token_hash', hash('sha256', $token))
            ->with(['assessment.client', 'assessment.professional', 'assessment.correctedBy', 'assessment.canceledBy'])
            ->firstOrFail();

        abort_if($share->revoked_at || $share->expires_at->isPast(), 410, 'Link expirado ou revogado.');

        $viewCount = $share->view_count + 1;
        $share->update([
            'viewed_at' => now(),
            'view_count' => $viewCount,
            'last_viewed_ip' => request()->ip(),
        ]);
        AppAudit::query()->create([
            'user_id' => null,
            'action' => 'bioimpedance_report_share.viewed',
            'auditable_type' => $share::class,
            'auditable_id' => $share->id,
            'description' => 'Relatório público visualizado',
            'new_values' => [
                'share_id' => $share->id,
                'assessment_id' => $share->bioimpedance_assessment_id,
                'view_count' => $viewCount,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        $assessment = $share->assessment;
        config(['dompdf.public_path' => public_path()]);

        return response()->view('bioimpedance.report-pdf', [
            'clinic' => [
                ...$this->clinicPayload(),
                'logo_data_uri' => $this->logoDataUri(),
            ],
            'client' => $this->clientPayload($assessment->client->setRelation('assessments', collect([$assessment]))),
            'assessment' => $this->assessmentPayload($assessment),
            'issuedAt' => $assessment->report_issued_at ?? $assessment->evaluated_at,
        ], 200, $this->reportSharePresenter->publicReportHeaders());
    }

    private function validateAssessment(Request $request, bool $requireClient = true, ?string $deviceModel = null): array
    {
        $isRelaxmedic = $deviceModel !== null
            ? $deviceModel === BioimpedanceClinicSetting::SCALE_MODEL_NAMES[BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC]
            : BioimpedanceClinicSetting::current()->scale_model === BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC;

        $request->merge([
            'weight_kg' => $this->normalizeDecimal($request->input('weight_kg')),
            'scale_bmi' => $this->normalizeDecimal($request->input('scale_bmi')),
            'body_fat_percentage' => $this->normalizeDecimal($request->input('body_fat_percentage')),
            'skeletal_muscle_percentage' => $this->normalizeDecimal($request->input('skeletal_muscle_percentage')),
            'muscle_rate_percentage' => $this->normalizeDecimal($request->input('muscle_rate_percentage')),
            'lean_body_mass_kg' => $this->normalizeDecimal($request->input('lean_body_mass_kg')),
            'subcutaneous_fat_percentage' => $this->normalizeDecimal($request->input('subcutaneous_fat_percentage')),
            'body_water_percentage' => $this->normalizeDecimal($request->input('body_water_percentage')),
            'muscle_mass_kg' => $this->normalizeDecimal($request->input('muscle_mass_kg')),
            'bone_mass_kg' => $this->normalizeDecimal($request->input('bone_mass_kg')),
            'protein_percentage' => $this->normalizeDecimal($request->input('protein_percentage')),
            'fat_mass_kg' => $this->normalizeDecimal($request->input('fat_mass_kg')),
            'water_weight_kg' => $this->normalizeDecimal($request->input('water_weight_kg')),
            'protein_mass_kg' => $this->normalizeDecimal($request->input('protein_mass_kg')),
            'ideal_body_weight_kg' => $this->normalizeDecimal($request->input('ideal_body_weight_kg')),
            'visceral_fat_level' => $this->normalizeDecimal($request->input('visceral_fat_level')),
        ]);

        return $request->validate([
            'bioimpedance_client_id' => [$requireClient ? 'required' : 'sometimes', 'exists:bioimpedance_clients,id'],
            'evaluated_at' => ['required', 'date', 'before_or_equal:now'],
            'weight_kg' => ['required', 'numeric', $isRelaxmedic ? 'between:2,300' : 'between:2,150'],
            'scale_bmi' => ['nullable', 'numeric', 'between:7,90'],
            'body_fat_percentage' => ['nullable', 'numeric', $isRelaxmedic ? 'between:0,100' : 'between:5,60'],
            'skeletal_muscle_percentage' => ['nullable', 'numeric', $isRelaxmedic ? 'between:0,100' : 'between:5,50'],
            'muscle_rate_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'lean_body_mass_kg' => ['nullable', 'numeric', 'between:0,300'],
            'subcutaneous_fat_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'body_water_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'muscle_mass_kg' => ['nullable', 'numeric', 'between:0,300'],
            'bone_mass_kg' => ['nullable', 'numeric', 'between:0,50'],
            'protein_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'fat_mass_kg' => ['nullable', 'numeric', 'between:0,300'],
            'water_weight_kg' => ['nullable', 'numeric', 'between:0,300'],
            'protein_mass_kg' => ['nullable', 'numeric', 'between:0,300'],
            'ideal_body_weight_kg' => ['nullable', 'numeric', 'between:2,300'],
            'obesity_level' => ['nullable', 'string', 'max:100'],
            'body_type' => ['nullable', 'string', 'max:100'],
            'resting_metabolism_kcal' => ['nullable', 'integer', $isRelaxmedic ? 'between:100,10000' : 'between:385,3999'],
            'body_age' => ['nullable', 'integer', $isRelaxmedic ? 'between:1,120' : 'between:18,80'],
            'visceral_fat_level' => ['nullable', $isRelaxmedic ? 'numeric' : 'integer', $isRelaxmedic ? 'between:0,100' : 'between:1,30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'bioimpedance_client_id.required' => 'Selecione um cliente antes de salvar a avaliação.',
            'bioimpedance_client_id.exists' => 'O cliente selecionado não foi encontrado.',
            'evaluated_at.required' => 'Informe a data e hora da avaliação.',
            'evaluated_at.date' => 'Informe uma data e hora de avaliação válida.',
            'evaluated_at.before_or_equal' => 'A avaliação não pode ser registrada no futuro. Confira a data e o horário informados.',
            'weight_kg.required' => 'Informe o peso exibido pela balança.',
            'weight_kg.numeric' => 'Informe o peso em kg. Exemplo: 95,2.',
            'weight_kg.between' => $isRelaxmedic ? 'O peso deve estar entre 2 kg e 300 kg.' : 'O peso deve estar entre 2 kg e 150 kg para a Omron HBF-514C.',
            'scale_bmi.numeric' => 'Informe o IMC da balança com número válido. Exemplo: 31,4.',
            'scale_bmi.between' => 'O IMC da balança deve estar entre 7 e 90.',
            'body_fat_percentage.numeric' => 'Informe a gordura corporal em percentual. Exemplo: 20,5.',
            'body_fat_percentage.between' => $isRelaxmedic ? 'A gordura corporal deve estar entre 0% e 100%.' : 'A gordura corporal deve estar entre 5% e 60%.',
            'skeletal_muscle_percentage.numeric' => 'Informe o músculo esquelético em percentual. Exemplo: 37,6.',
            'skeletal_muscle_percentage.between' => $isRelaxmedic ? 'O músculo esquelético deve estar entre 0% e 100%.' : 'O músculo esquelético deve estar entre 5% e 50%.',
            'resting_metabolism_kcal.integer' => 'Informe o metabolismo basal em kcal, sem casas decimais.',
            'resting_metabolism_kcal.between' => $isRelaxmedic ? 'O metabolismo basal deve estar entre 100 e 10000 kcal.' : 'O metabolismo basal deve estar entre 385 e 3999 kcal.',
            'body_age.integer' => 'Informe a idade corporal em anos, sem casas decimais.',
            'body_age.between' => $isRelaxmedic ? 'A idade corporal deve estar entre 1 e 120 anos.' : 'A idade corporal deve estar entre 18 e 80 anos.',
            'visceral_fat_level.numeric' => 'Informe a gordura visceral como número válido. Exemplo: 8,6.',
            'visceral_fat_level.integer' => 'Informe a gordura visceral da Omron como número inteiro, de 1 a 30.',
            'visceral_fat_level.between' => $isRelaxmedic ? 'A gordura visceral deve estar entre 0 e 100.' : 'A gordura visceral deve estar entre 1 e 30.',
            'notes.max' => 'A observação da avaliação pode ter no máximo 2000 caracteres.',
        ]);
    }

    private function clinicPayload(?BioimpedanceClinicSetting $settings = null): array
    {
        return [
            'name' => config('app.name', 'Clínica'),
            ...($settings ?? BioimpedanceClinicSetting::current())->payload(),
        ];
    }

    private function validateClient(Request $request, ?BioimpedanceClient $client = null): array
    {
        $request->merge([
            'height_cm' => $this->normalizeHeightToCentimeters($request->input('height_cm')),
            'phone_digits' => $this->normalizeDigits($request->input('phone')),
            'cpf' => $this->normalizeDigits($request->input('cpf')),
            'emergency_contact_phone' => $this->normalizeDigits($request->input('emergency_contact_phone')),
            'consent_accepted_at' => $request->boolean('consent_accepted')
                ? ($client?->consent_accepted_at?->toDateTimeString() ?? now()->toDateTimeString())
                : null,
        ]);

        return $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'birth_date' => ['required', 'date', 'before:today'],
            'biological_sex' => ['required', Rule::in(['female', 'male'])],
            'height_cm' => ['required', 'numeric', 'between:100,199.5'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_digits' => ['nullable', 'string', 'max:20', Rule::unique('bioimpedance_clients', 'phone_digits')->ignore($client)],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('bioimpedance_clients', 'email')->ignore($client)],
            'cpf' => ['nullable', 'string', 'size:11', Rule::unique('bioimpedance_clients', 'cpf')->ignore($client)],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'consent_accepted_at' => ['nullable', 'date'],
            'next_assessment_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'height_cm.between' => 'Informe a altura em metros ou centimetros. Exemplo: 1,74 ou 174.',
            'height_cm.numeric' => 'Informe uma altura valida. Exemplo: 1,74 ou 174.',
            'phone_digits.unique' => 'Ja existe um cliente cadastrado com este telefone.',
            'email.unique' => 'Ja existe um cliente cadastrado com este e-mail.',
            'cpf.size' => 'Informe o CPF com 11 digitos.',
            'cpf.unique' => 'Ja existe um cliente cadastrado com este CPF.',
        ]);
    }

    private function validateEvaluationDateAgainstBirthDate(BioimpedanceClient $client, string $evaluatedAt): void
    {
        if (Carbon::parse($evaluatedAt)->lt($client->birth_date)) {
            throw ValidationException::withMessages([
                'evaluated_at' => 'A avaliação não pode ser anterior ao nascimento do cliente.',
            ]);
        }
    }

    private function ensureClientIsNotAnonymized(BioimpedanceClient $client): void
    {
        if ($client->anonymized_at) {
            throw ValidationException::withMessages([
                'bioimpedance_client_id' => 'Cliente anonimizado não pode ser alterado.',
            ]);
        }
    }

    private function assessmentSnapshot(BioimpedanceClient $client, string $evaluatedAt): array
    {
        $settings = BioimpedanceClinicSetting::current();

        return [
            'age_at_assessment' => (int) $client->birth_date->diffInYears(Carbon::parse($evaluatedAt)),
            'height_cm_at_assessment' => (float) $client->height_cm,
            'biological_sex_at_assessment' => $client->biological_sex,
            'device_model' => $settings->scaleModelName(),
            'reference_version' => BioimpedanceAnalyzer::REFERENCE_VERSION,
        ];
    }

    private function assessmentSnapshotForCorrection(BioimpedanceAssessment $assessment, BioimpedanceClient $client, string $evaluatedAt): array
    {
        return [
            'age_at_assessment' => (int) $client->birth_date->diffInYears(Carbon::parse($evaluatedAt)),
            'height_cm_at_assessment' => (float) ($assessment->height_cm_at_assessment ?? $client->height_cm),
            'biological_sex_at_assessment' => $assessment->biological_sex_at_assessment ?? $client->biological_sex,
            'device_model' => $assessment->device_model ?? BioimpedanceAnalyzer::DEVICE_MODEL,
            'reference_version' => $assessment->reference_version ?? BioimpedanceAnalyzer::REFERENCE_VERSION,
        ];
    }

    private function assessmentAuditValues(BioimpedanceAssessment $assessment): array
    {
        return collect($assessment->only([
            'evaluated_at',
            'device_model',
            'weight_kg',
            'scale_bmi',
            'calculated_bmi',
            'bmi_difference',
            'body_fat_percentage',
            'skeletal_muscle_percentage',
            'muscle_rate_percentage',
            'lean_body_mass_kg',
            'subcutaneous_fat_percentage',
            'body_water_percentage',
            'muscle_mass_kg',
            'bone_mass_kg',
            'protein_percentage',
            'fat_mass_kg',
            'water_weight_kg',
            'protein_mass_kg',
            'ideal_body_weight_kg',
            'obesity_level',
            'body_type',
            'resting_metabolism_kcal',
            'body_age',
            'visceral_fat_level',
            'analysis',
            'source_metadata',
            'notes',
            'correction_count',
            'canceled_at',
            'cancellation_reason',
        ]))->map(fn ($value) => $value instanceof Carbon ? $value->toIso8601String() : $value)->all();
    }

    private function auditAssessment(BioimpedanceAssessment $assessment, Request $request, string $action, string $reason, array $oldValues, array $newValues): void
    {
        BioimpedanceAssessmentAudit::query()->create([
            'bioimpedance_assessment_id' => $assessment->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'reason' => $reason,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
        ]);
    }

    private function normalizeHeightToCentimeters(mixed $value): mixed
    {
        $normalized = $this->normalizeDecimal($value);

        if ($normalized === null || $normalized === '') {
            return $normalized;
        }

        $height = (float) $normalized;

        return $height <= 3 ? round($height * 100, 2) : round($height, 2);
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_string($value)) {
            return str_replace(',', '.', trim($value));
        }

        return $value;
    }

    private function normalizeDigits(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Apenas administradores podem executar esta ação.');
    }

    private function authorizeWrite(Request $request): void
    {
        abort_if($request->user()->role === User::ROLE_VIEWER, 403, 'Usuário de visualização não pode alterar registros.');
    }

    private function authorizeCreateClinicalRecords(Request $request): void
    {
        abort_unless($request->user()->canCreateClinicalRecords(), 403, 'Usuário sem permissão para criar registros clínicos.');
    }

    private function authorizeClinicalProfessional(Request $request): void
    {
        abort_unless($request->user()->isClinicalProfessional(), 403, 'Apenas profissionais e administradores podem executar esta ação.');
    }

    private function validateUser(Request $request, ?User $user = null, bool $requirePassword = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => [$requirePassword ? 'required' : 'nullable', 'string', 'min:8', 'max:160'],
        ];

        $validated = $request->validate($rules);
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        return $validated;
    }

    private function usersPayload(): array
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->userPayload($user))
            ->all();
    }

    private function userPayload(User $user): array
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

    private function clientAuditPayload(BioimpedanceClient $client): array
    {
        return collect($client->only([
            'full_name',
            'birth_date',
            'biological_sex',
            'height_cm',
            'phone',
            'phone_digits',
            'email',
            'cpf',
            'address',
            'emergency_contact_name',
            'emergency_contact_phone',
            'consent_accepted_at',
            'next_assessment_at',
            'inactivated_at',
            'privacy_exported_at',
            'privacy_export_count',
            'anonymized_at',
            'anonymized_by_user_id',
            'notes',
        ]))->map(fn ($value) => $value instanceof Carbon ? $value->toIso8601String() : $value)->all();
    }

    private function auditEventsPayload(): array
    {
        return AppAudit::query()
            ->with('user')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AppAudit $audit) => [
                'id' => $audit->id,
                'user_name' => $audit->user?->name ?? 'Sistema',
                'action' => $audit->action,
                'description' => $audit->description,
                'ip_address' => $audit->ip_address,
                'created_at' => $audit->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function adminDashboardPayload(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $today = $now->copy()->startOfDay();
        $upcomingLimit = $today->copy()->addDays(14);

        $monthlyAssessments = BioimpedanceAssessment::query()
            ->whereNull('canceled_at')
            ->whereBetween('evaluated_at', [$monthStart, $monthEnd]);

        $clientsWithAssessmentThisMonth = BioimpedanceAssessment::query()
            ->whereNull('canceled_at')
            ->whereBetween('evaluated_at', [$monthStart, $monthEnd])
            ->distinct()
            ->pluck('bioimpedance_client_id');

        $returningClientsThisMonth = BioimpedanceClient::query()
            ->whereIn('id', $clientsWithAssessmentThisMonth)
            ->whereHas('assessments', fn ($query) => $query
                ->whereNull('canceled_at')
                ->where('evaluated_at', '<', $monthStart))
            ->count();

        $clientsWithoutReturnQuery = BioimpedanceClient::query()
            ->whereNull('inactivated_at')
            ->whereNull('anonymized_at')
            ->whereNotNull('next_assessment_at')
            ->whereDate('next_assessment_at', '<', $today)
            ->whereDoesntHave('assessments', fn ($query) => $query
                ->whereNull('canceled_at')
                ->whereColumn('evaluated_at', '>=', 'bioimpedance_clients.next_assessment_at'))
            ->orderBy('next_assessment_at');

        $clientsWithoutReturnCount = (clone $clientsWithoutReturnQuery)->count();
        $clientsWithoutReturn = $clientsWithoutReturnQuery
            ->limit(5)
            ->get();

        $upcomingReassessmentsQuery = BioimpedanceClient::query()
            ->whereNull('inactivated_at')
            ->whereNull('anonymized_at')
            ->whereBetween('next_assessment_at', [$today->toDateString(), $upcomingLimit->toDateString()])
            ->orderBy('next_assessment_at');

        $upcomingReassessmentsCount = (clone $upcomingReassessmentsQuery)->count();
        $upcomingReassessments = $upcomingReassessmentsQuery
            ->limit(5)
            ->get();

        $professionalCounts = BioimpedanceAssessment::query()
            ->with('professional')
            ->whereNull('canceled_at')
            ->whereBetween('evaluated_at', [$monthStart, $monthEnd])
            ->get()
            ->groupBy(fn (BioimpedanceAssessment $assessment) => $assessment->professional?->name ?? 'Sem profissional')
            ->map(fn ($assessments, string $name) => [
                'name' => $name,
                'assessments_count' => $assessments->count(),
            ])
            ->values()
            ->sortByDesc('assessments_count')
            ->values()
            ->all();

        $recentAssessments = BioimpedanceAssessment::query()
            ->with(['client', 'professional'])
            ->whereNull('canceled_at')
            ->latest('evaluated_at')
            ->limit(6)
            ->get()
            ->map(fn (BioimpedanceAssessment $assessment) => [
                'id' => $assessment->id,
                'client_name' => $assessment->client?->full_name,
                'professional_name' => $assessment->professional?->name ?? 'Sem profissional',
                'evaluated_at' => $assessment->evaluated_at?->toIso8601String(),
                'weight_kg' => (float) $assessment->weight_kg,
                'calculated_bmi' => (float) $assessment->calculated_bmi,
            ])
            ->all();

        return [
            'period_label' => $now->translatedFormat('F/Y'),
            'generated_at' => $now->toIso8601String(),
            'cards' => [
                'assessments_this_month' => (clone $monthlyAssessments)->count(),
                'clients_new_this_month' => BioimpedanceClient::query()
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
                'returning_clients_this_month' => $returningClientsThisMonth,
                'clients_without_return' => $clientsWithoutReturnCount,
                'upcoming_reassessments' => $upcomingReassessmentsCount,
                'evolutions_registered' => BioimpedanceClient::query()
                    ->whereNull('inactivated_at')
                    ->whereNull('anonymized_at')
                    ->has('assessments', '>=', 2, 'and', fn ($query) => $query->whereNull('canceled_at'))
                    ->count(),
                'reports_issued_this_month' => AppAudit::query()
                    ->where('action', 'bioimpedance_assessment.report_downloaded')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
                'shares_sent_this_month' => BioimpedanceReportShare::query()
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
            ],
            'average_assessments_per_professional' => count($professionalCounts)
                ? round((clone $monthlyAssessments)->count() / count($professionalCounts), 1)
                : 0,
            'professionals' => $professionalCounts,
            'clients_without_return' => $clientsWithoutReturn
                ->map(fn (BioimpedanceClient $client) => $this->adminClientReminderPayload($client))
                ->all(),
            'upcoming_reassessments' => $upcomingReassessments
                ->map(fn (BioimpedanceClient $client) => $this->adminClientReminderPayload($client))
                ->all(),
            'recent_assessments' => $recentAssessments,
        ];
    }

    private function adminClientReminderPayload(BioimpedanceClient $client): array
    {
        return [
            'id' => $client->id,
            'full_name' => $client->full_name,
            'phone' => $client->phone,
            'email' => $client->email,
            'next_assessment_at' => $client->next_assessment_at?->toDateString(),
        ];
    }

    private function revokeClientPublicShares(BioimpedanceClient $client): void
    {
        BioimpedanceReportShare::query()
            ->whereHas('assessment', fn ($query) => $query->where('bioimpedance_client_id', $client->id))
            ->update([
                'message' => 'Compartilhamento revogado por anonimização LGPD.',
                'revoked_at' => now(),
            ]);
    }

    private function scrubClientAuditPersonalData(BioimpedanceClient $client): void
    {
        $assessmentIds = $client->assessments()->pluck('id');
        $shareIds = BioimpedanceReportShare::query()
            ->whereIn('bioimpedance_assessment_id', $assessmentIds)
            ->pluck('id');

        AppAudit::query()
            ->where(function ($query) use ($client, $shareIds): void {
                $query->where(function ($nested) use ($client): void {
                    $nested->where('auditable_type', $client::class)
                        ->where('auditable_id', $client->id);
                })->orWhere(function ($nested) use ($shareIds): void {
                    $nested->where('auditable_type', BioimpedanceReportShare::class)
                        ->whereIn('auditable_id', $shareIds);
                });
            })
            ->update([
                'description' => 'Dados pessoais removidos por anonimização LGPD.',
                'old_values' => null,
                'new_values' => ['anonymized' => true],
                'ip_address' => null,
                'user_agent' => null,
            ]);
    }

    private function audit(Request $request, string $action, Model $auditable, string $description, ?array $oldValues, ?array $newValues): void
    {
        AppAudit::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    private function clientPayload(BioimpedanceClient $client): array
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
                ->map(fn (BioimpedanceAssessment $assessment) => $this->assessmentPayload($assessment))
                ->all(),
        ];
    }

    private function loadClientAssessments(BioimpedanceClient $client): BioimpedanceClient
    {
        return $client->load(['assessments' => fn ($query) => $query->with('shares')->latest('evaluated_at')]);
    }

    private function assessmentPayload(BioimpedanceAssessment $assessment): array
    {
        $analysis = $assessment->analysis;

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
            'obesity_level' => $assessment->obesity_level,
            'body_type' => $assessment->body_type,
            'resting_metabolism_kcal' => $assessment->resting_metabolism_kcal,
            'body_age' => $assessment->body_age,
            'visceral_fat_level' => $assessment->visceral_fat_level === null ? null : (float) $assessment->visceral_fat_level,
            'analysis' => $analysis,
            'source_metadata' => $assessment->source_metadata,
            'notes' => $assessment->notes,
            'correction_count' => $assessment->correction_count,
            'canceled_at' => $assessment->canceled_at?->toIso8601String(),
            'cancellation_reason' => $assessment->cancellation_reason,
            'is_canceled' => $assessment->canceled_at !== null,
            'report_issued_at' => $assessment->report_issued_at?->toIso8601String(),
            'report_issue_count' => $assessment->report_issue_count,
            'shares' => $assessment->relationLoaded('shares')
                ? $assessment->shares->sortByDesc('created_at')->values()->map(fn (BioimpedanceReportShare $share) => $this->reportSharePresenter->payload($share))->all()
                : [],
        ];
    }

    private function logoDataUri(): ?string
    {
        $logoUrl = BioimpedanceClinicSetting::current()->logo_url ?? BioimpedanceClinicSetting::DEFAULTS['logo_url'];
        $path = public_path(ltrim((string) parse_url($logoUrl, PHP_URL_PATH), '/'));

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    private function pdfFileName(BioimpedanceAssessment $assessment): string
    {
        return Str::slug('bioimpedancia-'.$assessment->client->full_name.'-'.$assessment->evaluated_at->format('Y-m-d')).'.pdf';
    }

    private function privacyExportFileName(BioimpedanceClient $client): string
    {
        return Str::slug('dados-lgpd-'.$client->full_name.'-'.now()->format('Y-m-d')).'.json';
    }
}
