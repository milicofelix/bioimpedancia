<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceAssessmentAudit;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use App\Models\User;
use App\Services\Bioimpedance\BioimpedanceAnalyzer;
use App\Services\Bioimpedance\LegacyBioimpedanceAnalysisRefresher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BioimpedanceController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = BioimpedanceClient::query()
            ->with(['assessments' => fn ($query) => $query->latest('evaluated_at')])
            ->orderBy('full_name')
            ->get()
            ->map(fn (BioimpedanceClient $client) => $this->clientPayload($client));

        return response()->json([
            'clients' => $clients,
            'clinic' => $this->clinicPayload(),
            'current_user' => $this->userPayload(request()->user()),
            'users' => request()->user()->isAdmin() ? $this->usersPayload() : [],
            'audit_events' => request()->user()->isAdmin() ? $this->auditEventsPayload() : [],
        ]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);
        $validated = $this->validateClient($request);

        $client = BioimpedanceClient::query()->create($validated);
        $this->audit($request, 'bioimpedance_client.created', $client, 'Cliente cadastrado', null, $this->clientAuditPayload($client));

        return response()->json([
            'client' => $this->clientPayload($client->load('assessments')),
        ], 201);
    }

    public function updateClient(Request $request, BioimpedanceClient $client): JsonResponse
    {
        $this->authorizeWrite($request);
        $validated = $this->validateClient($request, $client);
        $oldValues = $this->clientAuditPayload($client);

        $client->update($validated);
        $this->audit($request, 'bioimpedance_client.updated', $client, 'Cliente atualizado', $oldValues, $this->clientAuditPayload($client->refresh()));

        return response()->json([
            'client' => $this->clientPayload($client->refresh()->load(['assessments' => fn ($query) => $query->latest('evaluated_at')])),
        ]);
    }

    public function inactivateClient(Request $request, BioimpedanceClient $client): JsonResponse
    {
        $this->authorizeWrite($request);
        $oldValues = $this->clientAuditPayload($client);
        $client->update([
            'inactivated_at' => now(),
        ]);
        $this->audit($request, 'bioimpedance_client.inactivated', $client, 'Cliente inativado', $oldValues, $this->clientAuditPayload($client->refresh()));

        return response()->json([
            'client' => $this->clientPayload($client->refresh()->load(['assessments' => fn ($query) => $query->latest('evaluated_at')])),
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

    public function storeAssessment(Request $request, BioimpedanceAnalyzer $analyzer): JsonResponse
    {
        $this->authorizeWrite($request);
        $validated = $this->validateAssessment($request);

        $client = BioimpedanceClient::query()->findOrFail($validated['bioimpedance_client_id']);
        if ($client->inactivated_at) {
            throw ValidationException::withMessages([
                'bioimpedance_client_id' => 'Cliente inativo não pode receber novas avaliações.',
            ]);
        }

        $this->validateEvaluationDateAgainstBirthDate($client, $validated['evaluated_at']);

        $snapshot = $this->assessmentSnapshot($client, $validated['evaluated_at']);
        $analysis = $analyzer->analyze($client->toArray(), [...$validated, ...$snapshot]);

        $assessment = BioimpedanceAssessment::query()->create([
            ...$validated,
            ...$snapshot,
            'user_id' => $request->user()->id,
            'calculated_bmi' => $analysis['calculated_bmi'],
            'bmi_difference' => $analysis['bmi_difference'],
            'analysis' => $analysis,
        ]);

        return response()->json([
            'client' => $this->clientPayload($client->refresh()->load(['assessments' => fn ($query) => $query->latest('evaluated_at')])),
            'assessment' => $this->assessmentPayload($assessment),
        ], 201);
    }

    public function updateAssessment(Request $request, BioimpedanceAssessment $assessment, BioimpedanceAnalyzer $analyzer): JsonResponse
    {
        $this->authorizeWrite($request);
        if ($assessment->canceled_at) {
            throw ValidationException::withMessages([
                'bioimpedance_assessment_id' => 'Avaliação cancelada não pode ser alterada.',
            ]);
        }

        $validated = $this->validateAssessment($request, requireClient: false);
        unset($validated['bioimpedance_client_id']);

        $changeReason = $request->validate([
            'change_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ])['change_reason'];

        $client = $assessment->client;
        $this->validateEvaluationDateAgainstBirthDate($client, $validated['evaluated_at']);

        $snapshot = $this->assessmentSnapshotForCorrection($assessment, $client, $validated['evaluated_at']);
        $analysis = $analyzer->analyze($client->toArray(), [...$validated, ...$snapshot]);
        $oldValues = $this->assessmentAuditValues($assessment);

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

        return response()->json([
            'client' => $this->clientPayload($client->refresh()->load(['assessments' => fn ($query) => $query->latest('evaluated_at')])),
            'assessment' => $this->assessmentPayload($assessment),
        ]);
    }

    public function cancelAssessment(Request $request, BioimpedanceAssessment $assessment): JsonResponse
    {
        $this->authorizeWrite($request);
        if ($assessment->canceled_at) {
            return response()->json([
                'client' => $this->clientPayload($assessment->client->load(['assessments' => fn ($query) => $query->latest('evaluated_at')])),
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
            'client' => $this->clientPayload($assessment->client->refresh()->load(['assessments' => fn ($query) => $query->latest('evaluated_at')])),
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

    private function validateAssessment(Request $request, bool $requireClient = true): array
    {
        $request->merge([
            'weight_kg' => $this->normalizeDecimal($request->input('weight_kg')),
            'scale_bmi' => $this->normalizeDecimal($request->input('scale_bmi')),
            'body_fat_percentage' => $this->normalizeDecimal($request->input('body_fat_percentage')),
            'skeletal_muscle_percentage' => $this->normalizeDecimal($request->input('skeletal_muscle_percentage')),
            'visceral_fat_level' => $this->normalizeDecimal($request->input('visceral_fat_level')),
        ]);

        return $request->validate([
            'bioimpedance_client_id' => [$requireClient ? 'required' : 'sometimes', 'exists:bioimpedance_clients,id'],
            'evaluated_at' => ['required', 'date', 'before_or_equal:now'],
            'weight_kg' => ['required', 'numeric', 'between:2,150'],
            'scale_bmi' => ['nullable', 'numeric', 'between:7,90'],
            'body_fat_percentage' => ['nullable', 'numeric', 'between:5,60'],
            'skeletal_muscle_percentage' => ['nullable', 'numeric', 'between:5,50'],
            'resting_metabolism_kcal' => ['nullable', 'integer', 'between:385,3999'],
            'body_age' => ['nullable', 'integer', 'between:18,80'],
            'visceral_fat_level' => ['nullable', 'integer', 'between:1,30'],
            'notes' => ['nullable', 'string', 'max:2000'],
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

    private function assessmentSnapshot(BioimpedanceClient $client, string $evaluatedAt): array
    {
        return [
            'age_at_assessment' => (int) $client->birth_date->diffInYears(Carbon::parse($evaluatedAt)),
            'height_cm_at_assessment' => (float) $client->height_cm,
            'biological_sex_at_assessment' => $client->biological_sex,
            'device_model' => BioimpedanceAnalyzer::DEVICE_MODEL,
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
            'weight_kg',
            'scale_bmi',
            'calculated_bmi',
            'bmi_difference',
            'body_fat_percentage',
            'skeletal_muscle_percentage',
            'resting_metabolism_kcal',
            'body_age',
            'visceral_fat_level',
            'analysis',
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
            'resting_metabolism_kcal' => $assessment->resting_metabolism_kcal,
            'body_age' => $assessment->body_age,
            'visceral_fat_level' => $assessment->visceral_fat_level === null ? null : (float) $assessment->visceral_fat_level,
            'analysis' => $analysis,
            'notes' => $assessment->notes,
            'correction_count' => $assessment->correction_count,
            'canceled_at' => $assessment->canceled_at?->toIso8601String(),
            'cancellation_reason' => $assessment->cancellation_reason,
            'is_canceled' => $assessment->canceled_at !== null,
            'report_issued_at' => $assessment->report_issued_at?->toIso8601String(),
            'report_issue_count' => $assessment->report_issue_count,
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
}
