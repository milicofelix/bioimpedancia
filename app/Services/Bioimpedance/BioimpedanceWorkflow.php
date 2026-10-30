<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanReportLayout;
use App\Domain\Bioimpedance\BodyscanExtendedMetricCatalog;
use App\Domain\Bioimpedance\BodyscanOcrProfileRegistry;
use App\Domain\Bioimpedance\ScaleModel;
use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceAssessmentAudit;
use App\Models\Bioimpedance\BioimpedanceImpedanceReading;
use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use App\Models\Bioimpedance\BioimpedanceReportShare;
use App\Models\Screening\ScreeningSession;
use App\Models\User;
use App\Services\Screening\ScreeningPrivacyService;
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
        private readonly ScreeningPrivacyService $screeningPrivacyService,
        private readonly BodyscanOcrProfileRegistry $bodyscanOcrProfiles,
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
            'screenings' => ScreeningSession::query()
                ->with('answers')
                ->where('bioimpedance_client_id', $client->id)
                ->oldest()
                ->get()
                ->map(fn (ScreeningSession $session): array => [
                    'questionnaire' => $session->questionnaire_name,
                    'version' => $session->questionnaire_version,
                    'status' => $session->status,
                    'requested_by' => $session->requested_by_name,
                    'minor_flow' => $session->is_minor_at_application,
                    'guardian_name' => $session->guardian_name,
                    'guardian_relationship' => $session->guardian_relationship,
                    'consent_accepted_at' => $session->consent_accepted_at?->toIso8601String(),
                    'guardian_consent_accepted_at' => $session->guardian_consent_accepted_at?->toIso8601String(),
                    'finalized_at' => $session->finalized_at?->toIso8601String(),
                    'total_score' => $session->total_score,
                    'result_description' => $session->result_description,
                    'answers' => $session->answers->map(fn ($answer): array => [
                        'question_code' => $answer->question_code,
                        'question' => $answer->question_text_snapshot,
                        'response' => $answer->response_label_snapshot,
                        'score' => $answer->score,
                    ])->values(),
                ])->values(),
            'clinic' => $this->clinicPayload(),
            'purpose' => 'Exportação de dados pessoais e avaliações para atendimento de solicitação LGPD.',
        ];

        return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->privacyExportFileName($client).'"',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
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

        DB::transaction(function () use ($request, $client, $anonymizedName): void {
            $screeningsAnonymized = $this->screeningPrivacyService->anonymizeClientScreenings(
                $client,
                'Solicitação LGPD vinculada à anonimização do cadastro do cliente.',
            );

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
                'screenings_anonymized' => $screeningsAnonymized,
                'reason_registered' => true,
            ]);
        });

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

    public function storeAssessment(Request $request, BioimpedanceAnalyzer $analyzer, RelaxmedicImageProcessor $imageProcessor, BodyscanProImageProcessor $bodyscanProcessor): JsonResponse
    {
        $this->authorizeCreateClinicalRecords($request);
        $scaleModel = BioimpedanceClinicSetting::current()->scale_model;

        $sourceMetadata = null;
        $segmentalMeasurements = [];
        $extendedMetrics = [];
        $impedanceReadings = [];
        $measurementProfile = [];

        if ($scaleModel === BioimpedanceClinicSetting::SCALE_MODEL_BODYSCAN_PRO) {
            $this->authorizeClinicalProfessional($request);
            [$sourceMetadata, $segmentalMeasurements, $measurementProfile, $extendedMetrics, $impedanceReadings] = $this->validateBodyscanReview($request, $bodyscanProcessor);
            $request->validate(collect($sourceMetadata['available_metric_fields'])
                ->mapWithKeys(fn (string $field) => [$field => ['required']])->all(), [], [
                'weight_kg' => 'peso',
                'scale_bmi' => 'IMC',
            ]);

            if (in_array('obesity_level', $sourceMetadata['available_metric_fields'], true)
                && ! $this->relaxmedicValueNormalizer->recognizesObesityLevel($request->input('obesity_level'))) {
                throw ValidationException::withMessages([
                    'obesity_level' => 'Selecione um nível de obesidade reconhecido depois de conferir a imagem.',
                ]);
            }
        }

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

        $snapshot = $this->assessmentSnapshot($client, $validated['evaluated_at'], $measurementProfile);
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

            $assessment = DB::transaction(function () use ($validated, $snapshot, $request, $analysis, $sourceMetadata, $segmentalMeasurements, $extendedMetrics, $impedanceReadings): BioimpedanceAssessment {
                $assessment = BioimpedanceAssessment::query()->create([
                    ...$validated,
                    ...$snapshot,
                    'user_id' => $request->user()->id,
                    'calculated_bmi' => $analysis['calculated_bmi'],
                    'bmi_difference' => $analysis['bmi_difference'],
                    'analysis' => $analysis,
                    'source_metadata' => $sourceMetadata,
                ]);

                if ($segmentalMeasurements !== []) {
                    $assessment->segmentalMeasurements()->createMany($segmentalMeasurements);
                }

                if ($extendedMetrics !== []) {
                    $assessment->extendedMetrics()->createMany($extendedMetrics);
                }

                if ($impedanceReadings !== []) {
                    $assessment->impedanceReadings()->createMany($impedanceReadings);
                }

                $assessment->load(['segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);

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
        $assessment->loadMissing('segmentalMeasurements');
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

            $assessment->refresh()->load('segmentalMeasurements');
            $this->auditAssessment($assessment, $request, 'corrected', $changeReason, $oldValues, $this->assessmentAuditValues($assessment));
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
        $assessment->load(['client', 'professional', 'correctedBy', 'canceledBy', 'segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);
        $legacyAnalysisRefresher->refreshIfLegacy($assessment);
        $assessment->refresh()->load(['client', 'professional', 'correctedBy', 'canceledBy', 'segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);
        $assessment->update([
            'report_issued_at' => now(),
            'report_issue_count' => $assessment->report_issue_count + 1,
        ]);
        $assessment->refresh()->load(['client', 'professional', 'correctedBy', 'canceledBy', 'segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);
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

        $segmentalSources = collect(data_get($output->request->structured_context, 'segmental_analysis.current', []))
            ->concat(data_get($output->request->structured_context, 'segmental_analysis.previous_same_model.measurements', []))
            ->filter(fn (array $measurement) => $usedSourceIds->contains($measurement['evidence_id'] ?? null))
            ->map(fn (array $measurement) => [
                'id' => $measurement['evidence_id'],
                'document' => 'Avaliação Bodyscan Pro',
                'version' => $measurement['reference_version'] ?? $assessment->reference_version,
                'section' => $this->segmentalAuditLabel($measurement),
                'url' => null,
                'checksum' => null,
                'claim_ids' => collect($output->structured_claims ?? [])
                    ->filter(fn (array $claim) => in_array($measurement['evidence_id'], $claim['source_ids'] ?? [], true))
                    ->pluck('id')->values()->all(),
            ])->values()->all();
        $sources = [...$sources, ...$segmentalSources];

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
            ->with(['assessment.client', 'assessment.professional', 'assessment.correctedBy', 'assessment.canceledBy', 'assessment.segmentalMeasurements', 'assessment.extendedMetrics', 'assessment.impedanceReadings'])
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

    private function validateBodyscanReview(Request $request, BodyscanProImageProcessor $processor): array
    {
        $submittedMetadata = $request->input('source_metadata');
        if (is_array($submittedMetadata) && ! $processor->verifyMetadata($submittedMetadata)) {
            throw ValidationException::withMessages([
                'source_metadata' => 'A extração Bodyscan foi alterada. Processe novamente a imagem.',
            ]);
        }

        $validated = $request->validate([
            'bodyscan_review_confirmed' => ['accepted'],
            'athlete_mode_at_assessment' => ['required', 'boolean'],
            'measurement_application' => ['required', Rule::in(ScaleModel::BODYSCAN_APPLICATIONS)],
            'measurement_protocol' => ['required', 'array:'.implode(',', ScaleModel::BODYSCAN_PROTOCOL_KEYS)],
            'measurement_protocol.profile_confirmed' => ['accepted'],
            'measurement_protocol.athlete_mode_confirmed' => ['accepted'],
            'measurement_protocol.electrodes_contact_confirmed' => ['accepted'],
            'measurement_protocol.comparable_conditions_confirmed' => ['accepted'],
            'source_metadata' => ['required', 'array'],
            'source_metadata.type' => ['required', Rule::in(['bodyscan_pro_image_extraction'])],
            'source_metadata.name' => ['required', 'string', 'max:255'],
            'source_metadata.size_bytes' => ['required', 'integer', 'between:1,10485760'],
            'source_metadata.mime_type' => ['required', Rule::in(['image/jpeg'])],
            'source_metadata.stored' => ['required', 'declined'],
            'source_metadata.sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'source_metadata.processed_at' => ['required', 'date'],
            'source_metadata.processor_model' => ['required', 'string', 'max:100'],
            'source_metadata.reference_version' => ['required', 'string', 'max:40'],
            'source_metadata.source_application' => ['required', Rule::in(ScaleModel::BODYSCAN_APPLICATIONS)],
            'source_metadata.report_layout' => ['required', Rule::in(BodyscanReportLayout::LAYOUTS)],
            'source_metadata.layout_profile_version' => ['required', 'string', 'max:50'],
            'source_metadata.layout_evidence' => ['required', 'array', 'min:3', 'max:20'],
            'source_metadata.layout_evidence.*' => ['required', 'string', 'max:200'],
            'source_metadata.available_metric_fields' => ['required', 'array', 'min:1'],
            'source_metadata.available_metric_fields.*' => ['required', 'distinct', Rule::in(BodyscanOcrProfileRegistry::METRIC_FIELDS)],
            'source_metadata.weight_unit' => ['required', Rule::in(['kg'])],
            'source_metadata.measured_at' => ['nullable', 'date'],
            'source_metadata.metrics' => ['required', 'array'],
            'source_metadata.extended_metrics' => ['present', 'array', 'max:30'],
            'source_metadata.extended_metrics.*.metric_code' => ['required', 'distinct', Rule::in(BodyscanExtendedMetricCatalog::CODES)],
            'source_metadata.extended_metrics.*.original_label' => ['required', 'string', 'max:160'],
            'source_metadata.extended_metrics.*.numeric_value' => ['nullable', 'numeric'],
            'source_metadata.extended_metrics.*.text_value' => ['nullable', 'string', 'max:2000'],
            'source_metadata.extended_metrics.*.unit' => ['nullable', 'string', 'max:30'],
            'source_metadata.extended_metrics.*.application_classification' => ['nullable', 'string', 'max:120'],
            'source_metadata.extended_metrics.*.printed_minimum' => ['nullable', 'numeric'],
            'source_metadata.extended_metrics.*.printed_maximum' => ['nullable', 'numeric'],
            'source_metadata.extended_metrics.*.weight_proportion_percentage' => ['nullable', 'numeric'],
            'source_metadata.extended_metrics.*.source_application' => ['required', Rule::in(ScaleModel::BODYSCAN_APPLICATIONS)],
            'source_metadata.extended_metrics.*.source_layout' => ['required', Rule::in(BodyscanReportLayout::LAYOUTS)],
            'source_metadata.extended_metrics.*.raw_extracted_value' => ['required', 'string', 'max:2000'],
            'source_metadata.extended_metrics.*.reference_version' => ['required', 'string', 'max:60'],
            'source_metadata.impedance_readings' => ['present', 'array', 'max:10'],
            'source_metadata.impedance_readings.*.frequency_khz' => ['required', 'integer', Rule::in(BioimpedanceImpedanceReading::FREQUENCIES_KHZ)],
            'source_metadata.impedance_readings.*.region' => ['required', Rule::in(BioimpedanceImpedanceReading::REGIONS)],
            'source_metadata.impedance_readings.*.side' => ['required', Rule::in(BioimpedanceImpedanceReading::SIDES)],
            'source_metadata.impedance_readings.*.impedance_ohms' => ['nullable', 'numeric', 'between:0.01,5000'],
            'source_metadata.impedance_readings.*.raw_value' => ['nullable', 'string', 'max:255'],
            'source_metadata.impedance_readings.*.reading_state' => ['required', Rule::in(BioimpedanceImpedanceReading::STATES)],
            'source_metadata.impedance_readings.*.device_version' => ['required', 'string', 'max:80'],
            'source_metadata.impedance_readings.*.source_application' => ['required', Rule::in(ScaleModel::BODYSCAN_APPLICATIONS)],
            'source_metadata.impedance_readings.*.source_layout' => ['required', Rule::in(BodyscanReportLayout::LAYOUTS)],
            'source_metadata.segmental_measurements' => ['required', 'array', 'size:10'],
            'source_metadata.raw_values' => ['present', 'array'],
            'source_metadata.raw_values.metrics' => ['required', 'array'],
            'source_metadata.raw_values.metric_source_labels' => ['required', 'array'],
            'source_metadata.raw_values.metric_text' => ['required', 'array'],
            'source_metadata.raw_values.impedance_readings' => ['present', 'array'],
            'source_metadata.raw_values.segmental_measurements' => ['required', 'array'],
            'source_metadata.detected_sections' => ['required', 'array', 'min:2'],
            'source_metadata.field_statuses' => ['present', 'array'],
            'source_metadata.missing_fields' => ['present', 'array'],
            'source_metadata.suspicious_values' => ['present', 'array'],
            'source_metadata.quality_issues' => ['present', 'array'],
            'source_metadata.side_consistency' => ['required', 'array'],
            'source_metadata.side_consistency.left_marker_visible' => ['required', 'boolean'],
            'source_metadata.side_consistency.right_marker_visible' => ['required', 'boolean'],
            'source_metadata.side_consistency.possible_swap' => ['required', 'boolean'],
            'source_metadata.side_consistency.reason' => ['nullable', 'string', 'max:500'],
            'source_metadata.signature' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'segmental_measurements' => ['required', 'array', 'size:10'],
            'segmental_measurements.*.type' => ['required', Rule::in(BioimpedanceSegmentalMeasurement::TYPES)],
            'segmental_measurements.*.region' => ['required', Rule::in(BioimpedanceSegmentalMeasurement::REGIONS)],
            'segmental_measurements.*.side' => ['required', Rule::in(BioimpedanceSegmentalMeasurement::SIDES)],
            'segmental_measurements.*.mass_kg' => ['required', 'numeric', 'between:0,300'],
            'segmental_measurements.*.reference_percentage' => ['required', 'numeric', 'between:0,1000'],
            'segmental_measurements.*.classification' => ['required', Rule::in(BioimpedanceSegmentalMeasurement::CLASSIFICATIONS)],
            'segmental_measurements.*.reference_min_percentage' => ['required', 'numeric', 'between:0,1000'],
            'segmental_measurements.*.reference_max_percentage' => ['required', 'numeric', 'between:0,1000'],
            'impedance_readings' => ['present', 'array', 'max:10'],
            'impedance_readings.*.frequency_khz' => ['required', 'integer', Rule::in(BioimpedanceImpedanceReading::FREQUENCIES_KHZ)],
            'impedance_readings.*.region' => ['required', Rule::in(BioimpedanceImpedanceReading::REGIONS)],
            'impedance_readings.*.side' => ['required', Rule::in(BioimpedanceImpedanceReading::SIDES)],
            'impedance_readings.*.impedance_ohms' => ['nullable', 'numeric', 'between:0.01,5000'],
            'impedance_readings.*.reading_state' => ['required', Rule::in(BioimpedanceImpedanceReading::STATES)],
        ], [
            'bodyscan_review_confirmed.accepted' => 'Confirme que todos os dados foram comparados com a imagem.',
            'source_metadata.required' => 'Processe novamente a imagem Bodyscan Pro antes de salvar.',
            'source_metadata.weight_unit.required' => 'A unidade do peso precisa estar identificada como kg.',
            'source_metadata.weight_unit.in' => 'O sistema aceita relatórios Bodyscan somente em kg. Gere novamente o relatório nessa unidade.',
            'segmental_measurements.size' => 'Confira as cinco regiões de gordura e as cinco regiões musculares.',
            'segmental_measurements.*.required' => 'Todos os valores segmentares precisam ser conferidos e preenchidos.',
            'measurement_protocol.required' => 'Confirme o protocolo da medição antes de importar o relatório.',
            'measurement_protocol.*.accepted' => 'Todas as confirmações do protocolo são obrigatórias para registrar a avaliação.',
        ]);

        if (! $validated['source_metadata']['side_consistency']['left_marker_visible']
            || ! $validated['source_metadata']['side_consistency']['right_marker_visible']) {
            throw ValidationException::withMessages([
                'source_metadata' => 'Os marcadores L e R precisam estar legíveis. Envie uma imagem completa e mais nítida antes de criar a avaliação.',
            ]);
        }

        $sourceApplication = $validated['source_metadata']['source_application'] ?? null;
        $reportLayout = $validated['source_metadata']['report_layout'];
        if (! is_string($sourceApplication)
            || ! BodyscanReportLayout::belongsToApplication($reportLayout, $sourceApplication)
            || $validated['source_metadata']['layout_profile_version'] !== BodyscanReportLayout::profileVersion($reportLayout)) {
            throw ValidationException::withMessages([
                'source_metadata' => 'O aplicativo, o layout e a versão do perfil de leitura não correspondem. Processe novamente a imagem.',
            ]);
        }
        if ($validated['measurement_application'] !== $sourceApplication) {
            throw ValidationException::withMessages([
                'measurement_application' => 'O aplicativo confirmado não corresponde à marca identificada no relatório. Processe novamente uma imagem compatível.',
            ]);
        }
        $expectedMetricFields = $this->bodyscanOcrProfiles->forLayout($reportLayout)->availableMetricFields;
        if (array_values($validated['source_metadata']['available_metric_fields']) !== $expectedMetricFields) {
            throw ValidationException::withMessages([
                'source_metadata' => 'Os campos disponíveis não correspondem ao perfil OCR identificado. Processe novamente a imagem.',
            ]);
        }

        $allowedExtendedCodes = BodyscanExtendedMetricCatalog::availableForLayout($reportLayout);
        $submittedExtendedCodes = collect($validated['source_metadata']['extended_metrics'])->pluck('metric_code');
        $missingRequiredExtendedCodes = collect(BodyscanExtendedMetricCatalog::requiredForLayout($reportLayout))
            ->reject(fn (string $code) => $submittedExtendedCodes->contains($code))
            ->values();
        if ($missingRequiredExtendedCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'source_metadata' => 'O relatório não teve todos os indicadores estendidos obrigatórios lidos. Processe novamente uma imagem completa e nítida.',
            ]);
        }
        foreach ($validated['source_metadata']['extended_metrics'] as $index => $metric) {
            $isTextual = BodyscanExtendedMetricCatalog::isTextual($metric['metric_code']);
            if (! in_array($metric['metric_code'], $allowedExtendedCodes, true)
                || $metric['source_application'] !== $sourceApplication
                || $metric['source_layout'] !== $reportLayout
                || $metric['reference_version'] !== BodyscanExtendedMetricCatalog::REFERENCE_VERSION
                || ($isTextual ? blank($metric['text_value'] ?? null) : ! is_numeric($metric['numeric_value'] ?? null))
                || (isset($metric['printed_minimum'], $metric['printed_maximum']) && (float) $metric['printed_minimum'] > (float) $metric['printed_maximum'])) {
                throw ValidationException::withMessages([
                    "source_metadata.extended_metrics.{$index}" => 'A métrica estendida não corresponde ao aplicativo e ao layout identificados. Processe novamente a imagem.',
                ]);
            }
        }

        $isTechnicalLayout = $reportLayout === BodyscanReportLayout::FITDAYS_TECHNICAL;
        $expectedImpedanceKeys = collect(BioimpedanceImpedanceReading::FREQUENCIES_KHZ)
            ->flatMap(fn (int $frequency) => collect(BioimpedanceImpedanceReading::EXPECTED_POSITIONS)
                ->map(fn (array $position) => $frequency.'|'.$position['region'].'|'.$position['side']))
            ->sort()->values();
        $sourceImpedanceKeys = collect($validated['source_metadata']['impedance_readings'])
            ->map(fn (array $reading) => $reading['frequency_khz'].'|'.$reading['region'].'|'.$reading['side'])
            ->sort()->values();
        $reviewedImpedanceKeys = collect($validated['impedance_readings'])
            ->map(fn (array $reading) => $reading['frequency_khz'].'|'.$reading['region'].'|'.$reading['side'])
            ->sort()->values();
        if (($isTechnicalLayout && ($sourceImpedanceKeys->all() !== $expectedImpedanceKeys->all()
                || $reviewedImpedanceKeys->all() !== $expectedImpedanceKeys->all()))
            || (! $isTechnicalLayout && ($sourceImpedanceKeys->isNotEmpty() || $reviewedImpedanceKeys->isNotEmpty()))) {
            throw ValidationException::withMessages([
                'impedance_readings' => $isTechnicalLayout
                    ? 'Confira as cinco regiões nas frequências de 20 kHz e 100 kHz.'
                    : 'Este layout não possui tabela de impedância bioelétrica.',
            ]);
        }

        $sourceImpedance = collect($validated['source_metadata']['impedance_readings'])->keyBy(
            fn (array $reading) => $reading['frequency_khz'].'|'.$reading['region'].'|'.$reading['side'],
        );
        $impedanceReadings = collect($validated['impedance_readings'])->map(function (array $reading) use ($sourceImpedance, $sourceApplication, $reportLayout): array {
            $key = $reading['frequency_khz'].'|'.$reading['region'].'|'.$reading['side'];
            $original = $sourceImpedance->get($key, []);

            return [
                ...$reading,
                'raw_value' => $original['raw_value'] ?? null,
                'device_version' => BioimpedanceImpedanceReading::deviceVersion(),
                'source_application' => $sourceApplication,
                'source_layout' => $reportLayout,
            ];
        })->all();
        foreach ($impedanceReadings as $index => $reading) {
            if (($reading['reading_state'] === 'clear' && ! is_numeric($reading['impedance_ohms'] ?? null))
                || ($reading['reading_state'] === 'unreadable' && ($reading['impedance_ohms'] ?? null) !== null)) {
                throw ValidationException::withMessages([
                    "impedance_readings.{$index}.reading_state" => 'Leitura legível exige um valor; leitura ilegível deve permanecer sem valor numérico.',
                ]);
            }
        }
        $impedanceReviewCorrections = collect($impedanceReadings)->map(function (array $reading) use ($sourceImpedance): ?array {
            $key = $reading['frequency_khz'].'|'.$reading['region'].'|'.$reading['side'];
            $original = $sourceImpedance->get($key, []);
            $originalValue = $original['impedance_ohms'] ?? null;
            $confirmedValue = $reading['impedance_ohms'] ?? null;
            $sameValue = ($originalValue === null && $confirmedValue === null)
                || (is_numeric($originalValue) && is_numeric($confirmedValue) && abs((float) $originalValue - (float) $confirmedValue) < 0.001);
            $sameState = ($original['reading_state'] ?? null) === ($reading['reading_state'] ?? null);

            return $sameValue && $sameState ? null : [
                'frequency_khz' => $reading['frequency_khz'],
                'region' => $reading['region'],
                'side' => $reading['side'],
                'extracted' => ['impedance_ohms' => $originalValue, 'reading_state' => $original['reading_state'] ?? null],
                'confirmed' => ['impedance_ohms' => $confirmedValue, 'reading_state' => $reading['reading_state']],
            ];
        })->filter()->values()->all();

        $expectedKeys = collect(BioimpedanceSegmentalMeasurement::TYPES)
            ->flatMap(fn (string $type) => collect(BioimpedanceSegmentalMeasurement::EXPECTED_SEGMENTS)
                ->map(fn (array $segment) => $type.'|'.$segment['region'].'|'.$segment['side']))
            ->sort()->values();
        $submittedKeys = collect($validated['segmental_measurements'])
            ->map(fn (array $segment) => $segment['type'].'|'.$segment['region'].'|'.$segment['side'])
            ->sort()->values();

        if ($submittedKeys->duplicates()->isNotEmpty() || $submittedKeys->all() !== $expectedKeys->all()) {
            throw ValidationException::withMessages(['segmental_measurements' => 'As regiões segmentares estão duplicadas, ausentes ou com lados incompatíveis.']);
        }

        foreach ($validated['segmental_measurements'] as $index => $segment) {
            if ((float) $segment['reference_min_percentage'] > (float) $segment['reference_max_percentage']) {
                throw ValidationException::withMessages(["segmental_measurements.{$index}.reference_min_percentage" => 'O limite mínimo não pode ser maior que o máximo.']);
            }
        }

        $originals = collect($validated['source_metadata']['segmental_measurements'])->keyBy(
            fn (array $segment) => $segment['type'].'|'.$segment['region'].'|'.$segment['side'],
        );
        $segments = collect($validated['segmental_measurements'])->map(function (array $segment) use ($originals) {
            $key = $segment['type'].'|'.$segment['region'].'|'.$segment['side'];

            return [
                ...$segment,
                'reference_version' => BioimpedanceSegmentalMeasurement::defaultReferenceVersion(),
                'raw_value' => data_get($originals->get($key), 'raw_value'),
            ];
        })->all();
        $segmentalReviewCorrections = collect($segments)->map(function (array $segment) use ($originals): ?array {
            $key = $segment['type'].'|'.$segment['region'].'|'.$segment['side'];
            $original = $originals->get($key, []);
            $changes = collect(['mass_kg', 'reference_percentage', 'classification', 'reference_min_percentage', 'reference_max_percentage'])
                ->mapWithKeys(function (string $field) use ($original, $segment): array {
                    $before = $original[$field] ?? null;
                    $after = $segment[$field] ?? null;
                    $sameValue = $field === 'classification'
                        ? (string) $before === (string) $after
                        : (($before === null && $after === null)
                            || (is_numeric($before) && is_numeric($after) && abs((float) $before - (float) $after) < 0.001));

                    return $sameValue ? [] : [$field => ['extracted' => $before, 'confirmed' => $after]];
                })->all();

            return $changes === [] ? null : [
                'type' => $segment['type'],
                'region' => $segment['region'],
                'side' => $segment['side'],
                'changes' => $changes,
            ];
        })->filter()->values()->all();

        return [[
            'type' => 'bodyscan_pro_image_extraction',
            'image_name' => $validated['source_metadata']['name'],
            'image_size_bytes' => $validated['source_metadata']['size_bytes'],
            'image_mime_type' => 'image/jpeg',
            'image_sha256' => $validated['source_metadata']['sha256'],
            'image_stored' => false,
            'processed_at' => $validated['source_metadata']['processed_at'],
            'processor_model' => $validated['source_metadata']['processor_model'],
            'reference_version' => $validated['source_metadata']['reference_version'],
            'extracted_application' => $validated['source_metadata']['source_application'] ?? null,
            'report_layout' => $reportLayout,
            'layout_profile_version' => $validated['source_metadata']['layout_profile_version'],
            'layout_evidence' => $validated['source_metadata']['layout_evidence'],
            'available_metric_fields' => $validated['source_metadata']['available_metric_fields'],
            'extended_metric_codes' => collect($validated['source_metadata']['extended_metrics'])->pluck('metric_code')->values()->all(),
            'impedance_reading_count' => count($impedanceReadings),
            'weight_unit' => $validated['source_metadata']['weight_unit'],
            'confirmed_application' => $validated['measurement_application'],
            'athlete_mode_at_assessment' => (bool) $validated['athlete_mode_at_assessment'],
            'device_model_code' => ScaleModel::BODYSCAN_PRO_MODEL_CODE,
            'device_manual_version' => ScaleModel::BODYSCAN_PRO_MANUAL_VERSION,
            'measurement_protocol' => $validated['measurement_protocol'],
            'ocr_field_statuses' => $validated['source_metadata']['field_statuses'],
            'ocr_missing_fields' => $validated['source_metadata']['missing_fields'],
            'ocr_suspicious_values' => $validated['source_metadata']['suspicious_values'],
            'ocr_quality_issues' => $validated['source_metadata']['quality_issues'],
            'ocr_detected_sections' => $validated['source_metadata']['detected_sections'],
            'ocr_raw_values' => $validated['source_metadata']['raw_values'],
            'side_consistency' => $validated['source_metadata']['side_consistency'],
            'segmental_review_corrections' => $segmentalReviewCorrections,
            'impedance_review_corrections' => $impedanceReviewCorrections,
            'review_confirmed_at' => now()->toIso8601String(),
            'reviewed_by_user_id' => $request->user()->id,
        ], $segments, [
            'athlete_mode_at_assessment' => (bool) $validated['athlete_mode_at_assessment'],
            'measurement_application' => $validated['measurement_application'],
            'device_model_code' => ScaleModel::BODYSCAN_PRO_MODEL_CODE,
            'device_manual_version' => ScaleModel::BODYSCAN_PRO_MANUAL_VERSION,
            'measurement_protocol' => $validated['measurement_protocol'],
        ], $validated['source_metadata']['extended_metrics'], $impedanceReadings];
    }

    private function validateAssessment(Request $request, bool $requireClient = true, ?string $deviceModel = null): array
    {
        $isRelaxmedic = $deviceModel !== null
            ? in_array($deviceModel, [
                BioimpedanceClinicSetting::SCALE_MODEL_NAMES[BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC],
                BioimpedanceClinicSetting::SCALE_MODEL_NAMES[BioimpedanceClinicSetting::SCALE_MODEL_BODYSCAN_PRO],
            ], true)
            : in_array(BioimpedanceClinicSetting::current()->scale_model, [
                BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC,
                BioimpedanceClinicSetting::SCALE_MODEL_BODYSCAN_PRO,
            ], true);
        $isBodyscan = $deviceModel !== null
            ? $deviceModel === BioimpedanceClinicSetting::SCALE_MODEL_NAMES[BioimpedanceClinicSetting::SCALE_MODEL_BODYSCAN_PRO]
            : BioimpedanceClinicSetting::current()->scale_model === BioimpedanceClinicSetting::SCALE_MODEL_BODYSCAN_PRO;
        $weightRange = $isBodyscan
            ? 'between:'.ScaleModel::BODYSCAN_MIN_WEIGHT_KG.','.ScaleModel::BODYSCAN_MAX_WEIGHT_KG
            : ($isRelaxmedic ? 'between:2,300' : 'between:2,150');

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
            'weight_kg' => ['required', 'numeric', $weightRange],
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
            'weight_kg.between' => $isBodyscan
                ? 'O peso deve estar entre 5 kg e 180 kg para a Bodyscan Pro.'
                : ($isRelaxmedic ? 'O peso deve estar entre 2 kg e 300 kg.' : 'O peso deve estar entre 2 kg e 150 kg para a Omron HBF-514C.'),
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

    private function assessmentSnapshot(BioimpedanceClient $client, string $evaluatedAt, array $measurementProfile = []): array
    {
        $settings = BioimpedanceClinicSetting::current();

        return [
            'age_at_assessment' => (int) $client->birth_date->diffInYears(Carbon::parse($evaluatedAt)),
            'height_cm_at_assessment' => (float) $client->height_cm,
            'biological_sex_at_assessment' => $client->biological_sex,
            'device_model' => $settings->scaleModelName(),
            'reference_version' => $settings->scaleReferenceVersion(),
            'athlete_mode_at_assessment' => $measurementProfile['athlete_mode_at_assessment'] ?? null,
            'measurement_application' => $measurementProfile['measurement_application'] ?? null,
            'device_model_code' => $measurementProfile['device_model_code'] ?? null,
            'device_manual_version' => $measurementProfile['device_manual_version'] ?? null,
            'measurement_protocol' => $measurementProfile['measurement_protocol'] ?? null,
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
            'athlete_mode_at_assessment' => $assessment->athlete_mode_at_assessment,
            'measurement_application' => $assessment->measurement_application,
            'device_model_code' => $assessment->device_model_code,
            'device_manual_version' => $assessment->device_manual_version,
            'measurement_protocol' => $assessment->measurement_protocol,
        ];
    }

    private function assessmentAuditValues(BioimpedanceAssessment $assessment): array
    {
        $values = collect($assessment->only([
            'evaluated_at',
            'device_model',
            'athlete_mode_at_assessment',
            'measurement_application',
            'device_model_code',
            'device_manual_version',
            'measurement_protocol',
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

        if ($assessment->relationLoaded('segmentalMeasurements')) {
            $values['segmental_measurements'] = $assessment->segmentalMeasurements
                ->map(fn (BioimpedanceSegmentalMeasurement $measurement) => $measurement->only([
                    'type', 'region', 'side', 'mass_kg', 'reference_percentage', 'classification',
                    'reference_min_percentage', 'reference_max_percentage', 'reference_version',
                ]))->values()->all();
        }

        if ($assessment->relationLoaded('extendedMetrics')) {
            $values['extended_metrics'] = $assessment->extendedMetrics
                ->map(fn ($metric) => $metric->only([
                    'metric_code', 'original_label', 'numeric_value', 'text_value', 'unit',
                    'application_classification', 'printed_minimum', 'printed_maximum',
                    'weight_proportion_percentage', 'source_application', 'source_layout',
                    'raw_extracted_value', 'reference_version',
                ]))->values()->all();
        }

        if ($assessment->relationLoaded('impedanceReadings')) {
            $values['impedance_readings'] = $assessment->impedanceReadings
                ->map(fn (BioimpedanceImpedanceReading $reading) => $reading->only([
                    'frequency_khz', 'region', 'side', 'impedance_ohms', 'raw_value',
                    'reading_state', 'device_version', 'source_application', 'source_layout',
                ]))->values()->all();
        }

        return $values;
    }

    private function segmentalAuditLabel(array $measurement): string
    {
        $type = ($measurement['type'] ?? null) === BioimpedanceSegmentalMeasurement::TYPE_FAT ? 'Gordura' : 'Músculo';
        $region = match ([$measurement['region'] ?? null, $measurement['side'] ?? null]) {
            ['arm', 'left'] => 'braço esquerdo',
            ['arm', 'right'] => 'braço direito',
            ['trunk', 'center'] => 'tronco',
            ['leg', 'left'] => 'perna esquerda',
            ['leg', 'right'] => 'perna direita',
            default => 'região não identificada',
        };

        return $type.' segmentar - '.$region;
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
        $path = public_path('images/brand/bioflow-mark.png');

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
