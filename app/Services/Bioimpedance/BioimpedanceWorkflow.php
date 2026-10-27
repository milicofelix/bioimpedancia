<?php

namespace App\Services\Bioimpedance;

use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceAssessmentAudit;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use App\Models\Bioimpedance\BioimpedanceReportShare;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates the bioimpedance application use cases.
 *
 * HTTP concerns remain in the domain-specific controllers. This service keeps
 * the existing use-case contracts stable while the domain is decomposed into
 * focused collaborators.
 */
class BioimpedanceWorkflow
{
    public function __construct(
        private readonly BioimpedanceReportSharePresenter $reportSharePresenter,
        private readonly BioimpedanceReportComparison $reportComparison,
        private readonly RelaxmedicValueNormalizer $relaxmedicValueNormalizer,
        private readonly BioimpedanceWorkspacePresenter $workspacePresenter,
        private readonly BioimpedanceAdminDashboard $adminDashboard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            ...$this->operationalWorkspacePayload($request),
            'users' => $request->user()->isAdmin() ? $this->usersPayload() : [],
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
            'admin_dashboard' => $request->user()->isAdmin() ? $this->adminDashboard->payload() : null,
        ]);
    }

    public function operationalIndex(Request $request): JsonResponse
    {
        return response()->json($this->operationalWorkspacePayload($request));
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
        $scaleModel = BioimpedanceClinicSetting::current()->scale_model;

        if ($scaleModel === BioimpedanceClinicSetting::SCALE_MODEL_BODYSCAN_PRO) {
            throw ValidationException::withMessages([
                'scale_model' => 'O fluxo de avaliação da Bodyscan Pro ainda está em preparação. Selecione outro modelo para registrar uma avaliação.',
            ]);
        }

        $sourceMetadata = null;

        if ($scaleModel === BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC) {
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
                'source_metadata.raw_values' => ['present', 'array'],
                'source_metadata.raw_values.obesity_level' => ['sometimes', 'nullable', 'string', 'max:100'],
                'source_metadata.unrecognized_fields' => ['present', 'array'],
                'source_metadata.unrecognized_fields.*' => ['string', Rule::in(['obesity_level'])],
                'source_metadata.field_statuses' => ['present', 'array'],
                'source_metadata.field_statuses.obesity_level' => ['required', Rule::in(['recognized', 'unrecognized', 'missing'])],
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

            $submittedObesityLevel = $request->input('obesity_level');
            $requiresObesityLevelCorrection = in_array('obesity_level', $source['source_metadata']['unrecognized_fields'], true);
            if (($requiresObesityLevelCorrection || filled($submittedObesityLevel))
                && ! $this->relaxmedicValueNormalizer->recognizesObesityLevel($submittedObesityLevel)) {
                throw ValidationException::withMessages([
                    'obesity_level' => 'Selecione uma classificação reconhecida após conferir o texto original na imagem.',
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
                'ocr_raw_values' => $source['source_metadata']['raw_values'],
                'ocr_unrecognized_fields' => $source['source_metadata']['unrecognized_fields'],
                'ocr_field_statuses' => $source['source_metadata']['field_statuses'],
                'ocr_reviewed_values' => collect($source['source_metadata']['unrecognized_fields'])
                    ->mapWithKeys(fn (string $field) => [
                        $field => $field === 'obesity_level'
                            ? $this->relaxmedicValueNormalizer->obesityLevel($request->input($field))
                            : $request->input($field),
                    ])
                    ->all(),
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

        $creationLock = null;
        if ($sourceMetadata) {
            $creationLock = Cache::lock('bioimpedance-assessment:'.hash('sha256', $client->id.'|'.$sourceMetadata['image_sha256']), 15);
            if (! $creationLock->get()) {
                throw ValidationException::withMessages([
                    'source_metadata' => 'Esta imagem já está sendo usada para criar uma avaliação. Aguarde a conclusão.',
                ]);
            }
        }

        try {
            if ($sourceMetadata && BioimpedanceAssessment::query()
                ->where('bioimpedance_client_id', $client->id)
                ->where('source_metadata->image_sha256', $sourceMetadata['image_sha256'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'source_metadata' => 'Esta imagem já originou uma avaliação para o cliente selecionado.',
                ]);
            }

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
        } finally {
            $creationLock?->release();
        }

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($client->refresh())),
            'assessment' => $this->assessmentPayload($assessment),
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ], 201);
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
                'care_guidance' => null,
                'care_guidance_sources' => null,
                'care_guidance_approved_by_user_id' => null,
                'care_guidance_approved_at' => null,
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
            'comparison' => $this->reportComparison->for($assessment),
            'issuedAt' => $assessment->report_issued_at,
        ])->setPaper('a4');

        return request()->boolean('inline')
            ? $pdf->stream($this->pdfFileName($assessment))
            : $pdf->download($this->pdfFileName($assessment));
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

        DB::transaction(function () use ($assessment, $request, $validated): void {
            $oldValues = $this->assessmentAuditValues($assessment);
            $assessment->update([
                'notes' => $validated['notes'],
                'care_guidance' => null,
                'care_guidance_sources' => null,
                'care_guidance_approved_by_user_id' => null,
                'care_guidance_approved_at' => null,
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
                    ->where('validation_status', 'passed')
                    ->whereHas('request', fn ($query) => $query
                        ->where('bioimpedance_assessment_id', $assessment->id)
                        ->where('purpose', 'observation'))
                    ->update([
                        'professional_observation' => $validated['notes'],
                        'approved_by_user_id' => $request->user()->id,
                        'approved_at' => now(),
                    ]);
            }
        });

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($assessment->client->refresh())),
            'assessment' => $this->assessmentPayload($assessment),
            'audit_events' => $request->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ]);
    }

    public function suggestAssessmentCareGuidance(Request $request, BioimpedanceAssessment $assessment, BioimpedanceCareGuidanceAssistant $assistant): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        abort_if($assessment->canceled_at, 422, 'Avaliação cancelada não pode receber orientações.');
        abort_if(blank($assessment->notes), 422, 'A observação profissional precisa ser aprovada antes das orientações.');

        $hasApprovedObservation = BioimpedanceAiAnalysisOutput::query()
            ->whereNotNull('approved_at')
            ->whereHas('request', fn ($query) => $query->where('bioimpedance_assessment_id', $assessment->id)->where('purpose', 'observation'))
            ->exists();
        abort_unless($hasApprovedObservation, 422, 'A observação gerada precisa ser aprovada antes das orientações.');

        return response()->json(['assistant' => $assistant->suggest($assessment, $request->user())]);
    }

    public function approveAssessmentCareGuidance(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        $this->authorizeClinicalProfessional($request);
        abort_if($assessment->canceled_at, 422, 'Avaliação cancelada não pode receber orientações.');

        $validated = $request->validate([
            'care_guidance' => ['required', 'string', 'min:100', 'max:4000'],
            'review_action' => ['required', Rule::in(['approved', 'edited'])],
            'assistant_output_id' => ['required', 'integer', 'exists:bioimpedance_ai_analysis_outputs,id'],
        ]);

        $output = BioimpedanceAiAnalysisOutput::query()
            ->with(['request.sources.chunk.document'])
            ->whereKey($validated['assistant_output_id'])
            ->where('validation_status', 'passed')
            ->whereHas('request', fn ($query) => $query->where('bioimpedance_assessment_id', $assessment->id)->where('purpose', 'care_guidance'))
            ->firstOrFail();

        if (data_get($output->request->structured_context, 'assessment_updated_at') !== $assessment->updated_at?->toIso8601String()) {
            throw ValidationException::withMessages([
                'care_guidance' => 'A avaliação foi alterada depois da geração. Gere uma nova orientação antes de aprovar.',
            ]);
        }

        $usedSourceIds = collect($output->structured_claims ?? [])->pluck('source_ids')->flatten()->unique();
        $sources = $output->request->sources
            ->filter(fn ($source) => $usedSourceIds->contains($source->chunk?->chunk_key))
            ->map(fn ($source) => [
                'id' => data_get($source->source_snapshot, 'chunk_key', $source->chunk?->chunk_key),
                'document' => data_get($source->source_snapshot, 'document_title', $source->chunk?->document?->title),
                'version' => data_get($source->source_snapshot, 'document_version', $source->chunk?->document?->version),
                'section' => data_get($source->source_snapshot, 'section', $source->chunk?->section),
                'url' => data_get($source->source_snapshot, 'source_url', $source->chunk?->document?->source_url),
                'checksum' => data_get($source->source_snapshot, 'chunk_checksum', $source->chunk?->checksum),
                'claim_ids' => collect($output->structured_claims ?? [])
                    ->filter(fn (array $claim) => in_array($source->chunk?->chunk_key, $claim['source_ids'] ?? [], true))
                    ->pluck('id')->values()->all(),
            ])->filter(fn ($source) => filled($source['id']))->values()->all();

        DB::transaction(function () use ($assessment, $request, $validated, $output, $sources): void {
            $oldValues = $this->assessmentAuditValues($assessment);
            $assessment->update([
                'care_guidance' => $validated['care_guidance'],
                'care_guidance_sources' => $sources,
                'care_guidance_approved_by_user_id' => $request->user()->id,
                'care_guidance_approved_at' => now(),
            ]);
            $output->update([
                'care_guidance' => $validated['care_guidance'],
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $this->auditAssessment(
                $assessment->refresh(),
                $request,
                'care_guidance_'.$validated['review_action'],
                $validated['review_action'] === 'edited' ? 'Orientações do agente editadas e aprovadas pelo profissional.' : 'Orientações do agente aprovadas pelo profissional.',
                $oldValues,
                $this->assessmentAuditValues($assessment)
            );
        });

        return response()->json([
            'client' => $this->clientPayload($this->loadClientAssessments($assessment->client->refresh())),
            'assessment' => $this->assessmentPayload($assessment->refresh()),
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

    public function publicReport(Request $request, string $token): Response
    {
        $share = BioimpedanceReportShare::query()
            ->where('token_hash', hash('sha256', $token))
            ->with(['assessment.client', 'assessment.professional', 'assessment.correctedBy', 'assessment.canceledBy'])
            ->firstOrFail();

        abort_if($share->revoked_at || $share->expires_at->isPast(), 410, 'Link expirado ou revogado.');

        if (! $request->boolean('confirm')) {
            return response()
                ->view('bioimpedance.public-report-warning', [
                    'confirmUrl' => route('bioimpedance.public-report', $token).'?confirm=1',
                    'expiresAt' => $share->expires_at,
                ])
                ->withHeaders($this->reportSharePresenter->publicReportHeaders());
        }

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

        $pdf = Pdf::loadView('bioimpedance.report-pdf', [
            'clinic' => [
                ...$this->clinicPayload(),
                'logo_data_uri' => $this->logoDataUri(),
            ],
            'client' => $this->clientPayload($assessment->client->setRelation('assessments', collect([$assessment]))),
            'assessment' => $this->assessmentPayload($assessment),
            'comparison' => $this->reportComparison->for($assessment),
            'issuedAt' => $assessment->report_issued_at ?? $assessment->evaluated_at,
        ])->setPaper('a4');

        $response = $pdf->stream($this->pdfFileName($assessment));
        $response->headers->add($this->reportSharePresenter->publicReportHeaders());

        return $response;
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
            'obesity_level' => $isRelaxmedic
                ? $this->relaxmedicValueNormalizer->obesityLevel($request->input('obesity_level'))
                : $request->input('obesity_level'),
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
        return $this->workspacePresenter->clinic($settings);
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
            'reference_version' => $settings->scaleReferenceVersion(),
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
            'care_guidance',
            'care_guidance_sources',
            'care_guidance_approved_by_user_id',
            'care_guidance_approved_at',
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
        return $this->workspacePresenter->users();
    }

    private function userPayload(User $user): array
    {
        return $this->workspacePresenter->user($user);
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
        return $this->workspacePresenter->client($client);
    }

    private function loadClientAssessments(BioimpedanceClient $client): BioimpedanceClient
    {
        return $this->workspacePresenter->loadClientAssessments($client);
    }

    private function operationalWorkspacePayload(Request $request): array
    {
        return $this->workspacePresenter->workspace($request->user());
    }

    private function assessmentPayload(BioimpedanceAssessment $assessment): array
    {
        return $this->workspacePresenter->assessment($assessment);
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
