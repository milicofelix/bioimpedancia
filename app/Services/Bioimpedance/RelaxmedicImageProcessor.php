<?php

namespace App\Services\Bioimpedance;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RelaxmedicImageProcessor
{
    private const METRIC_FIELDS = [
        'weight_kg',
        'scale_bmi',
        'body_fat_percentage',
        'muscle_rate_percentage',
        'lean_body_mass_kg',
        'subcutaneous_fat_percentage',
        'visceral_fat_level',
        'body_water_percentage',
        'skeletal_muscle_percentage',
        'muscle_mass_kg',
        'bone_mass_kg',
        'protein_percentage',
        'resting_metabolism_kcal',
        'body_age',
        'fat_mass_kg',
        'water_weight_kg',
        'protein_mass_kg',
        'ideal_body_weight_kg',
        'obesity_level',
        'body_type',
    ];

    private const NUMERIC_RANGES = [
        'weight_kg' => [2, 300],
        'scale_bmi' => [7, 90],
        'body_fat_percentage' => [0, 100],
        'muscle_rate_percentage' => [0, 100],
        'lean_body_mass_kg' => [0, 300],
        'subcutaneous_fat_percentage' => [0, 100],
        'visceral_fat_level' => [0, 100],
        'body_water_percentage' => [0, 100],
        'skeletal_muscle_percentage' => [0, 100],
        'muscle_mass_kg' => [0, 300],
        'bone_mass_kg' => [0, 50],
        'protein_percentage' => [0, 100],
        'resting_metabolism_kcal' => [100, 10000],
        'body_age' => [1, 120],
        'fat_mass_kg' => [0, 300],
        'water_weight_kg' => [0, 300],
        'protein_mass_kg' => [0, 300],
        'ideal_body_weight_kg' => [2, 300],
    ];

    private const RECOGNITION_LABELS = [
        'peso',
        'imc',
        'gordura corporal',
        'taxa muscular',
        'massa corporal magra',
        'gordura subcutânea',
        'gordura visceral',
        'água corporal',
        'músculo esquelético',
        'massa muscular',
        'massa óssea',
        'proteína',
        'tmb',
        'idade do corpo',
        'massa gorda',
        'peso da água',
        'massa de proteína',
        'peso corporal ideal',
        'nível de obesidade',
        'tipo de corpo',
    ];

    public function process(UploadedFile $image): array
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('A leitura de imagem não está configurada. Informe a chave da OpenAI.');
        }

        $contents = $image->get();
        if ($contents === false || $contents === '') {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.openai.timeout', 45))
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                'model' => config('services.openai.vision_model', config('services.openai.model', 'gpt-5.1')),
                'store' => false,
                'max_output_tokens' => 3000,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->prompt(),
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => 'data:image/jpeg;base64,'.base64_encode($contents),
                            'detail' => 'high',
                        ],
                    ],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'relaxmedic_bioimpedance_extraction',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
            ])
            ->throw()
            ->json();

        $payload = json_decode($this->extractOutputText($response), true);
        if (! is_array($payload)) {
            throw new RuntimeException('O serviço de leitura retornou uma resposta inválida.');
        }

        $result = $this->normalize($payload, $image);
        $imageMetadata = [
            ...$result['image'],
            'sha256' => hash('sha256', $contents),
            'processed_at' => now()->toIso8601String(),
            'processor_model' => (string) config('services.openai.vision_model', config('services.openai.model', 'gpt-5.1')),
        ];
        $result['image'] = [
            ...$imageMetadata,
            'signature' => $this->signMetadata($imageMetadata),
        ];

        return $result;
    }

    public function verifyMetadata(array $metadata): bool
    {
        $signature = $metadata['signature'] ?? null;
        if (! is_string($signature) || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return false;
        }

        return hash_equals($this->signMetadata($metadata), $signature);
    }

    private function signMetadata(array $metadata): string
    {
        $payload = collect([
            'name',
            'size_bytes',
            'mime_type',
            'stored',
            'sha256',
            'processed_at',
            'processor_model',
        ])->mapWithKeys(fn (string $key) => [$key => $metadata[$key] ?? null])->all();

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) config('app.key'));
    }

    private function normalize(array $payload, UploadedFile $image): array
    {
        $detectedLabels = collect($payload['detected_labels'] ?? [])
            ->filter(fn ($label) => is_string($label))
            ->map(fn (string $label) => mb_strtolower(trim($label)))
            ->unique()
            ->values();

        $recognizedLabels = $detectedLabels->filter(
            fn (string $detected) => collect(self::RECOGNITION_LABELS)->contains(
                fn (string $expected) => str_contains($detected, $expected) || str_contains($expected, $detected)
            )
        );

        $rawMetrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        $metrics = [];
        foreach (self::METRIC_FIELDS as $field) {
            $value = $rawMetrics[$field] ?? null;
            $metrics[$field] = array_key_exists($field, self::NUMERIC_RANGES)
                ? $this->normalizeNumber($value)
                : $this->normalizeText($value);
        }

        $numericCount = collect(self::NUMERIC_RANGES)
            ->keys()
            ->filter(fn (string $field) => $metrics[$field] !== null)
            ->count();

        if (! ($payload['recognized'] ?? false) || $recognizedLabels->count() < 4 || $numericCount < 5 || $metrics['weight_kg'] === null) {
            throw new RuntimeException('A imagem não foi reconhecida como um relatório compatível da Relaxmedic/RelaxFit.');
        }

        $missingFields = collect(self::METRIC_FIELDS)
            ->filter(fn (string $field) => $metrics[$field] === null)
            ->values()
            ->all();

        $suspiciousValues = collect($payload['suspicious_values'] ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['field'] ?? null) && filled($item['reason'] ?? null))
            ->map(fn (array $item) => [
                'field' => (string) $item['field'],
                'value' => $item['value'] ?? null,
                'reason' => (string) $item['reason'],
            ]);

        foreach (self::NUMERIC_RANGES as $field => [$minimum, $maximum]) {
            $value = $metrics[$field];
            if ($value !== null && ($value < $minimum || $value > $maximum)) {
                $suspiciousValues->push([
                    'field' => $field,
                    'value' => $value,
                    'reason' => "Valor fora da faixa esperada ({$minimum} a {$maximum}).",
                ]);
            }
        }

        $measuredAt = $this->normalizeMeasuredAt($payload['measured_at'] ?? null, $suspiciousValues);

        return [
            'recognized' => true,
            'report_type' => $this->normalizeText($payload['report_type'] ?? null) ?? 'Relaxmedic/RelaxFit',
            'measured_at' => $measuredAt,
            'metrics' => $metrics,
            'missing_fields' => $missingFields,
            'suspicious_values' => $suspiciousValues->unique(fn (array $item) => $item['field'].'|'.$item['reason'])->values()->all(),
            'detected_labels' => $detectedLabels->all(),
            'image' => [
                'name' => $image->getClientOriginalName(),
                'size_bytes' => $image->getSize(),
                'mime_type' => $image->getMimeType(),
                'stored' => false,
            ],
        ];
    }

    private function normalizeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', trim($value));
        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace(['.', ','], ['', '.'], $normalized)
                : str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, 100);
    }

    private function normalizeMeasuredAt(mixed $value, $suspiciousValues): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $measuredAt = Carbon::parse($value, config('app.timezone'));
            if ($measuredAt->isFuture()) {
                $suspiciousValues->push([
                    'field' => 'measured_at',
                    'value' => $value,
                    'reason' => 'A data extraída está no futuro.',
                ]);
            }

            return $measuredAt->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            $suspiciousValues->push([
                'field' => 'measured_at',
                'value' => $value,
                'reason' => 'A data e hora não puderam ser interpretadas.',
            ]);

            return null;
        }
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Analise exclusivamente a imagem anexada. Ela deve ser um relatório de composição corporal do aplicativo RelaxFit/Relaxmedic, normalmente com o título "Dados medidos".
Identifique as métricas pelos textos dos rótulos, nunca apenas pela posição visual.
Não calcule, estime, deduza ou complete valores ausentes. Use null quando um rótulo ou valor não estiver legível.
Converta peso e massas para kg, percentuais para números sem o sinal %, TMB para kcal e gordura visceral/idade para valores numéricos.
Converta a data e hora exibidas para ISO 8601 local no formato YYYY-MM-DDTHH:MM:SS, sem inventar fuso horário.
Em detected_labels, copie apenas os nomes de métricas realmente visíveis.
Marque recognized=false se a imagem não for um relatório compatível, estiver ilegível ou não contiver rótulos suficientes.
Em suspicious_values, informe ambiguidades visuais, valores cortados, unidades inesperadas ou qualquer leitura de baixa confiança.
PROMPT;
    }

    private function responseSchema(): array
    {
        $nullableNumber = ['type' => ['number', 'null']];
        $nullableString = ['type' => ['string', 'null']];
        $metricProperties = [];

        foreach (self::METRIC_FIELDS as $field) {
            $metricProperties[$field] = array_key_exists($field, self::NUMERIC_RANGES)
                ? $nullableNumber
                : $nullableString;
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['recognized', 'report_type', 'measured_at', 'detected_labels', 'metrics', 'suspicious_values'],
            'properties' => [
                'recognized' => ['type' => 'boolean'],
                'report_type' => $nullableString,
                'measured_at' => $nullableString,
                'detected_labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                'metrics' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => self::METRIC_FIELDS,
                    'properties' => $metricProperties,
                ],
                'suspicious_values' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['field', 'value', 'reason'],
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'value' => ['type' => ['string', 'number', 'null']],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function extractOutputText(array $response): string
    {
        if (filled($response['output_text'] ?? null)) {
            return (string) $response['output_text'];
        }

        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && filled($content['text'] ?? null)) {
                    return (string) $content['text'];
                }
            }
        }

        return '';
    }
}
