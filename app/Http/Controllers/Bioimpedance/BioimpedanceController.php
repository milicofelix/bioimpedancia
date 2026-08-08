<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Services\Bioimpedance\BioimpedanceAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BioimpedanceController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = BioimpedanceClient::query()
            ->with(['assessments' => fn ($query) => $query->latest('evaluated_at')->limit(5)])
            ->orderBy('full_name')
            ->get()
            ->map(fn (BioimpedanceClient $client) => $this->clientPayload($client));

        return response()->json([
            'clients' => $clients,
            'clinic' => [
                'name' => config('app.name', 'Clínica'),
                'display_name' => 'Rico Style Emagrecimento',
                'contact' => 'Avaliação corporal e acompanhamento estético',
                'logo_initials' => 'RS',
            ],
        ]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $request->merge([
            'height_cm' => $this->normalizeHeightToCentimeters($request->input('height_cm')),
        ]);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'birth_date' => ['required', 'date', 'before:today'],
            'biological_sex' => ['required', Rule::in(['female', 'male'])],
            'height_cm' => ['required', 'numeric', 'between:80,250'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'height_cm.between' => 'Informe a altura em metros ou centimetros. Exemplo: 1,74 ou 174.',
            'height_cm.numeric' => 'Informe uma altura valida. Exemplo: 1,74 ou 174.',
        ]);

        $client = BioimpedanceClient::query()->create($validated);

        return response()->json([
            'client' => $this->clientPayload($client->load('assessments')),
        ], 201);
    }

    public function storeAssessment(Request $request, BioimpedanceAnalyzer $analyzer): JsonResponse
    {
        $request->merge([
            'weight_kg' => $this->normalizeDecimal($request->input('weight_kg')),
            'scale_bmi' => $this->normalizeDecimal($request->input('scale_bmi')),
            'body_fat_percentage' => $this->normalizeDecimal($request->input('body_fat_percentage')),
            'skeletal_muscle_percentage' => $this->normalizeDecimal($request->input('skeletal_muscle_percentage')),
            'visceral_fat_level' => $this->normalizeDecimal($request->input('visceral_fat_level')),
        ]);

        $validated = $request->validate([
            'bioimpedance_client_id' => ['required', 'exists:bioimpedance_clients,id'],
            'evaluated_at' => ['required', 'date'],
            'weight_kg' => ['required', 'numeric', 'between:20,300'],
            'scale_bmi' => ['nullable', 'numeric', 'between:5,90'],
            'body_fat_percentage' => ['nullable', 'numeric', 'between:1,80'],
            'skeletal_muscle_percentage' => ['nullable', 'numeric', 'between:1,80'],
            'resting_metabolism_kcal' => ['nullable', 'integer', 'between:500,5000'],
            'body_age' => ['nullable', 'integer', 'between:10,120'],
            'visceral_fat_level' => ['nullable', 'numeric', 'between:0,40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $client = BioimpedanceClient::query()->findOrFail($validated['bioimpedance_client_id']);
        $analysis = $analyzer->analyze($client->toArray(), $validated);

        $assessment = BioimpedanceAssessment::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'calculated_bmi' => $analysis['calculated_bmi'],
            'bmi_difference' => $analysis['bmi_difference'],
            'analysis' => $analysis,
        ]);

        return response()->json([
            'client' => $this->clientPayload($client->refresh()->load(['assessments' => fn ($query) => $query->latest('evaluated_at')->limit(5)])),
            'assessment' => $this->assessmentPayload($assessment),
        ], 201);
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
        return [
            'id' => $assessment->id,
            'bioimpedance_client_id' => $assessment->bioimpedance_client_id,
            'professional_name' => $assessment->professional?->name,
            'evaluated_at' => $assessment->evaluated_at?->toIso8601String(),
            'weight_kg' => (float) $assessment->weight_kg,
            'scale_bmi' => $assessment->scale_bmi === null ? null : (float) $assessment->scale_bmi,
            'calculated_bmi' => (float) $assessment->calculated_bmi,
            'bmi_difference' => $assessment->bmi_difference === null ? null : (float) $assessment->bmi_difference,
            'body_fat_percentage' => $assessment->body_fat_percentage === null ? null : (float) $assessment->body_fat_percentage,
            'skeletal_muscle_percentage' => $assessment->skeletal_muscle_percentage === null ? null : (float) $assessment->skeletal_muscle_percentage,
            'resting_metabolism_kcal' => $assessment->resting_metabolism_kcal,
            'body_age' => $assessment->body_age,
            'visceral_fat_level' => $assessment->visceral_fat_level === null ? null : (float) $assessment->visceral_fat_level,
            'analysis' => $assessment->analysis,
            'notes' => $assessment->notes,
        ];
    }
}
