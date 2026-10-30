<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanReportLayout;
use App\Domain\Bioimpedance\BodyscanOcrProfileRegistry;
use App\Domain\Bioimpedance\ScaleModel;
use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement as Segment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BodyscanProImageProcessor
{
    private const METRIC_FIELDS = BodyscanOcrProfileRegistry::METRIC_FIELDS;

    private const NUMERIC_RANGES = [
        'weight_kg' => [ScaleModel::BODYSCAN_MIN_WEIGHT_KG, ScaleModel::BODYSCAN_MAX_WEIGHT_KG],
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

    public function __construct(
        private readonly BioimpedanceJpegInspector $jpegInspector,
        private readonly RelaxmedicValueNormalizer $valueNormalizer,
        private readonly BodyscanSegmentalOcrCompleter $segmentalCompleter,
        private readonly BodyscanOcrProfileRegistry $ocrProfiles,
    ) {}

    public function process(UploadedFile $image): array
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('A leitura de imagem não está configurada. Informe a chave da OpenAI.');
        }

        $contents = $this->jpegInspector->contents($image);
        $processorModel = (string) config('services.openai.vision_model', config('services.openai.model', 'gpt-5.1'));
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) config('services.openai.bodyscan_ocr_timeout', 90))
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                'model' => $processorModel,
                'store' => false,
                'max_output_tokens' => 6500,
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $this->prompt()],
                        ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,'.base64_encode($contents), 'detail' => 'high'],
                    ],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'bodyscan_pro_bioimpedance_extraction',
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

        $payload['segmental_measurements'] = $this->segmentalCompleter->complete(
            $contents,
            $processorModel,
            is_array($payload['segmental_measurements'] ?? null) ? $payload['segmental_measurements'] : [],
            is_string($payload['report_layout'] ?? null) ? $payload['report_layout'] : null,
        );

        $result = $this->normalize($payload, $image);
        $imageMetadata = [
            'type' => 'bodyscan_pro_image_extraction',
            'name' => $image->getClientOriginalName(),
            'size_bytes' => strlen($contents),
            'mime_type' => 'image/jpeg',
            'stored' => false,
            'sha256' => hash('sha256', $contents),
            'processed_at' => now()->toIso8601String(),
            'processor_model' => $processorModel,
            'reference_version' => ScaleModel::BODYSCAN_REFERENCE_VERSION,
            'source_application' => $result['source_application'],
            'report_layout' => $result['report_layout'],
            'layout_profile_version' => $result['layout_profile_version'],
            'layout_evidence' => $result['layout_evidence'],
            'available_metric_fields' => $result['available_metric_fields'],
            'weight_unit' => $result['weight_unit'],
            'measured_at' => $result['measured_at'],
            'metrics' => $result['metrics'],
            'segmental_measurements' => $result['segmental_measurements'],
            'raw_values' => $result['raw_values'],
            'detected_sections' => $result['detected_sections'],
            'field_statuses' => $result['field_statuses'],
            'missing_fields' => $result['missing_fields'],
            'suspicious_values' => $result['suspicious_values'],
            'quality_issues' => $result['quality_issues'],
            'side_consistency' => $result['side_consistency'],
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

        return is_string($signature)
            && preg_match('/^[a-f0-9]{64}$/', $signature) === 1
            && hash_equals($this->signMetadata($metadata), $signature);
    }

    private function normalize(array $payload, UploadedFile $image): array
    {
        $detectedSections = collect($payload['detected_sections'] ?? [])
            ->filter(fn ($section) => is_string($section) && trim($section) !== '')
            ->map(fn (string $section) => $this->normalizedLabel($section))
            ->unique()
            ->values();
        $layoutEvidence = collect($payload['layout_evidence'] ?? [])
            ->filter(fn ($evidence) => is_string($evidence) && trim($evidence) !== '')
            ->map(fn (string $evidence) => mb_substr(trim($evidence), 0, 200))
            ->unique()
            ->values();
        $sourceApplication = $this->normalizeSourceApplication($payload['source_application'] ?? null);
        $reportLayout = BodyscanReportLayout::identify(
            $sourceApplication,
            $layoutEvidence->concat($detectedSections)->values()->all(),
        );
        $reportedLayout = $this->normalizeText($payload['report_layout'] ?? null);

        if (! ($payload['recognized'] ?? false)
            || $sourceApplication === null
            || $reportLayout === null
            || $reportedLayout !== $reportLayout
            || ! BodyscanReportLayout::belongsToApplication($reportLayout, $sourceApplication)) {
            throw new RuntimeException('A imagem não corresponde a um layout Bodyscan Pro compatível do RelaxFit ou Fitdays. Gere novamente um dos relatórios reconhecidos pelo sistema.');
        }

        $profile = $this->ocrProfiles->forLayout($reportLayout);
        $missingRequiredSections = $profile->missingRequiredSections($detectedSections->all());
        if ($missingRequiredSections !== []) {
            throw new RuntimeException('A imagem não possui todas as seções obrigatórias do perfil '.$reportLayout.': '.implode(', ', $missingRequiredSections).'.');
        }

        $weightUnit = $this->normalizeWeightUnit($payload['weight_unit'] ?? null);
        if ($weightUnit === null) {
            throw new RuntimeException('Não foi possível identificar a unidade do peso. Confira se o relatório exibe kg, lb ou stone e envie uma imagem mais nítida.');
        }
        if ($weightUnit !== 'kg') {
            throw new RuntimeException("O relatório está configurado em {$weightUnit}. Altere a unidade para kg no aplicativo e gere novamente o relatório; o sistema não converte valores automaticamente.");
        }

        $qualityIssues = collect($payload['quality_issues'] ?? [])
            ->filter(fn ($issue) => is_string($issue) && trim($issue) !== '')
            ->map(fn (string $issue) => trim($issue))
            ->filter(fn (string $issue) => $this->isVisualQualityIssue($issue))
            ->values();
        $missingFields = collect();
        $suspiciousValues = collect($payload['suspicious_values'] ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['field'] ?? null) && filled($item['reason'] ?? null))
            ->filter(fn (array $item) => $this->isReadingUncertainty((string) $item['reason']))
            ->map(fn (array $item) => [
                'field' => mb_substr((string) $item['field'], 0, 120),
                'value' => $item['value'] ?? null,
                'reason' => mb_substr((string) $item['reason'], 0, 500),
            ]);

        $rawMetrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        $rawMetricLabels = is_array($payload['metric_source_labels'] ?? null) ? $payload['metric_source_labels'] : [];
        $rawMetricTexts = is_array($payload['metric_raw_values'] ?? null) ? $payload['metric_raw_values'] : [];
        $metrics = [];
        $fieldStatuses = [];
        $fieldStatuses['source_application'] = 'recognized';
        $fieldStatuses['report_layout'] = 'recognized';
        foreach (self::METRIC_FIELDS as $field) {
            if (! $profile->supportsMetric($field)) {
                $metrics[$field] = null;
                $fieldStatuses['metrics.'.$field] = 'unavailable_in_layout';
                if (($rawMetrics[$field] ?? null) !== null) {
                    $suspiciousValues->push([
                        'field' => 'metrics.'.$field,
                        'value' => $rawMetrics[$field],
                        'reason' => 'O perfil identificado não oferece este campo; a leitura pode ter associado outro indicador ao rótulo incorreto.',
                    ]);
                }
                continue;
            }
            $rawValue = $rawMetrics[$field] ?? null;
            $metrics[$field] = array_key_exists($field, self::NUMERIC_RANGES)
                ? $this->normalizeNumber($rawValue)
                : $this->normalizeText($rawValue);
            $fieldStatuses['metrics.'.$field] = $metrics[$field] === null ? 'missing' : 'recognized';
            if ($metrics[$field] === null) {
                $missingFields->push('metrics.'.$field);
            } elseif (! $profile->recognizesMetricLabel($field, $rawMetricLabels[$field] ?? null)
                || ! is_string($rawMetricTexts[$field] ?? null)
                || trim($rawMetricTexts[$field]) === '') {
                $fieldStatuses['metrics.'.$field] = 'suspicious';
                $suspiciousValues->push([
                    'field' => 'metrics.'.$field,
                    'value' => $rawMetricLabels[$field] ?? null,
                    'reason' => 'O rótulo ou o texto bruto da métrica não corresponde aos aliases do perfil identificado.',
                ]);
            }
        }

        if ($metrics['obesity_level'] !== null) {
            $rawObesityLevel = $metrics['obesity_level'];
            if ($this->valueNormalizer->recognizesObesityLevel($rawObesityLevel)) {
                $metrics['obesity_level'] = $this->valueNormalizer->obesityLevel($rawObesityLevel);
            } else {
                $metrics['obesity_level'] = null;
                $fieldStatuses['metrics.obesity_level'] = 'unrecognized';
                $missingFields->push('metrics.obesity_level');
                $suspiciousValues->push([
                    'field' => 'metrics.obesity_level',
                    'value' => $rawObesityLevel,
                    'reason' => 'Classificação não reconhecida. Confira o texto original na imagem.',
                ]);
            }
        }

        foreach (self::NUMERIC_RANGES as $field => [$minimum, $maximum]) {
            $value = $metrics[$field];
            if ($value !== null && ($value < $minimum || $value > $maximum)) {
                $fieldStatuses['metrics.'.$field] = 'suspicious';
                $suspiciousValues->push([
                    'field' => 'metrics.'.$field,
                    'value' => $value,
                    'reason' => "Valor fora da faixa esperada ({$minimum} a {$maximum}).",
                ]);
            }
        }

        $segmentalMeasurements = $this->normalizeSegments(
            is_array($payload['segmental_measurements'] ?? null) ? $payload['segmental_measurements'] : [],
            $missingFields,
            $suspiciousValues,
            $fieldStatuses,
        );
        $this->appendConsistencyWarnings($metrics, $segmentalMeasurements, $suspiciousValues, $fieldStatuses);
        $sideConsistency = $this->sideConsistency($payload['side_consistency'] ?? [], $suspiciousValues);
        $measuredAt = $this->normalizeMeasuredAt($payload['measured_at'] ?? null, $suspiciousValues, $fieldStatuses);

        if ($measuredAt === null) {
            $missingFields->push('measured_at');
        }

        $hasConcreteReadingProblem = $qualityIssues->isNotEmpty()
            || $missingFields->isNotEmpty()
            || $suspiciousValues->isNotEmpty()
            || $sideConsistency['possible_swap'];

        if (! ($payload['is_complete'] ?? false) && $hasConcreteReadingProblem && $qualityIssues->isEmpty()) {
            $qualityIssues->push('O serviço identificou que alguma parte do relatório está cortada, borrada ou ilegível.');
        }

        $isComplete = $missingFields->isEmpty()
            && $suspiciousValues->isEmpty()
            && $qualityIssues->isEmpty()
            && ! $sideConsistency['possible_swap'];

        return [
            'recognized' => true,
            'is_complete' => $isComplete,
            'report_type' => 'Bodyscan Pro',
            'reference_version' => ScaleModel::BODYSCAN_REFERENCE_VERSION,
            'source_application' => $sourceApplication,
            'report_layout' => $reportLayout,
            'layout_profile_version' => BodyscanReportLayout::profileVersion($reportLayout),
            'layout_evidence' => $layoutEvidence->all(),
            'available_metric_fields' => $profile->availableMetricFields,
            'weight_unit' => $weightUnit,
            'measured_at' => $measuredAt,
            'metrics' => $metrics,
            'segmental_measurements' => $segmentalMeasurements,
            'side_consistency' => $sideConsistency,
            'missing_fields' => $missingFields->unique()->values()->all(),
            'suspicious_values' => $suspiciousValues->unique(fn (array $item) => $item['field'].'|'.$item['reason'])->values()->all(),
            'quality_issues' => $qualityIssues->unique()->values()->all(),
            'field_statuses' => $fieldStatuses,
            'raw_values' => [
                'metrics' => $rawMetrics,
                'metric_source_labels' => $rawMetricLabels,
                'metric_text' => $rawMetricTexts,
                'segmental_measurements' => $payload['segmental_measurements'] ?? [],
            ],
            'detected_sections' => $detectedSections->all(),
            'allowed_values' => [
                'obesity_level' => $this->valueNormalizer->obesityLevelOptions(),
                'segment_classification' => Segment::CLASSIFICATION_LABELS,
            ],
            'image' => [
                'name' => $image->getClientOriginalName(),
                'size_bytes' => $image->getSize(),
                'mime_type' => $image->getMimeType(),
                'stored' => false,
            ],
        ];
    }

    private function normalizeSegments(array $rawSegments, $missingFields, $suspiciousValues, array &$fieldStatuses): array
    {
        $indexed = [];
        foreach ($rawSegments as $rawSegment) {
            if (! is_array($rawSegment)) {
                continue;
            }

            $type = $this->normalizeText($rawSegment['type'] ?? null);
            $region = $this->normalizeText($rawSegment['region'] ?? null);
            $side = $this->normalizeText($rawSegment['side'] ?? null);
            $key = $type.'|'.$region.'|'.$side;
            if (! in_array($type, Segment::TYPES, true)
                || ! $this->isExpectedSegment($region, $side)
                || array_key_exists($key, $indexed)) {
                $suspiciousValues->push([
                    'field' => 'segmental_measurements',
                    'value' => $rawSegment['label'] ?? $key,
                    'reason' => array_key_exists($key, $indexed)
                        ? 'Segmento duplicado na leitura.'
                        : 'Região, lado ou tipo segmentar não reconhecido.',
                ]);
                continue;
            }

            $indexed[$key] = $rawSegment;
        }

        return collect(Segment::TYPES)->flatMap(function (string $type) use ($indexed, $missingFields, $suspiciousValues, &$fieldStatuses) {
            return collect(Segment::EXPECTED_SEGMENTS)->map(function (array $expected) use ($type, $indexed, $missingFields, $suspiciousValues, &$fieldStatuses) {
                $key = $type.'|'.$expected['region'].'|'.$expected['side'];
                $fieldPrefix = 'segmental.'.$type.'.'.$expected['region'].'.'.$expected['side'];
                $raw = $indexed[$key] ?? [];
                $massKg = $this->normalizeNumber($raw['mass_kg'] ?? null);
                $referencePercentage = $this->normalizeNumber($raw['reference_percentage'] ?? null);
                $referenceMin = $this->normalizeNumber($raw['reference_min_percentage'] ?? null);
                $referenceMax = $this->normalizeNumber($raw['reference_max_percentage'] ?? null);
                $rawClassification = $this->normalizeText($raw['classification'] ?? null);
                $classification = $this->normalizeClassification($rawClassification);
                $legibility = $this->normalizeText($raw['legibility'] ?? null) ?? 'unreadable';

                foreach (['mass_kg' => $massKg, 'reference_percentage' => $referencePercentage] as $field => $value) {
                    $statusKey = $fieldPrefix.'.'.$field;
                    $fieldStatuses[$statusKey] = $value === null ? 'missing' : ($legibility === 'clear' ? 'recognized' : 'suspicious');
                    if ($value === null) {
                        $missingFields->push($statusKey);
                    }
                }

                if ($rawClassification !== null && $classification === null) {
                    $fieldStatuses[$fieldPrefix.'.classification'] = 'unrecognized';
                    $missingFields->push($fieldPrefix.'.classification');
                    $suspiciousValues->push([
                        'field' => $fieldPrefix.'.classification',
                        'value' => $rawClassification,
                        'reason' => 'Classificação segmentar não reconhecida.',
                    ]);
                } else {
                    $fieldStatuses[$fieldPrefix.'.classification'] = $classification === null ? 'missing' : 'recognized';

                    if ($classification === null) {
                        $missingFields->push($fieldPrefix.'.classification');
                    }
                }

                foreach (['reference_min_percentage' => $referenceMin, 'reference_max_percentage' => $referenceMax] as $field => $value) {
                    $statusKey = $fieldPrefix.'.'.$field;
                    $fieldStatuses[$statusKey] = $value === null ? 'missing' : 'recognized';

                    if ($value === null) {
                        $missingFields->push($statusKey);
                    }
                }

                foreach (['mass_kg' => [$massKg, 0, 300], 'reference_percentage' => [$referencePercentage, 0, 1000]] as $field => [$value, $minimum, $maximum]) {
                    if ($value !== null && ($value < $minimum || $value > $maximum)) {
                        $fieldStatuses[$fieldPrefix.'.'.$field] = 'suspicious';
                        $suspiciousValues->push([
                            'field' => $fieldPrefix.'.'.$field,
                            'value' => $value,
                            'reason' => "Valor segmentar fora da faixa esperada ({$minimum} a {$maximum}).",
                        ]);
                    }
                }

                if ($referenceMin !== null && $referenceMax !== null && $referenceMin > $referenceMax) {
                    $fieldStatuses[$fieldPrefix.'.reference_min_percentage'] = 'suspicious';
                    $fieldStatuses[$fieldPrefix.'.reference_max_percentage'] = 'suspicious';
                    $suspiciousValues->push([
                        'field' => $fieldPrefix.'.reference_range',
                        'value' => $referenceMin.'-'.$referenceMax,
                        'reason' => 'O limite mínimo da referência é maior que o limite máximo.',
                    ]);
                }

                if ($legibility !== 'clear' && ($massKg !== null || $referencePercentage !== null)) {
                    $suspiciousValues->push([
                        'field' => $fieldPrefix,
                        'value' => $raw['raw_value'] ?? null,
                        'reason' => 'A leitura deste segmento foi marcada como incerta ou ilegível.',
                    ]);
                }

                return [
                    'type' => $type,
                    'region' => $expected['region'],
                    'side' => $expected['side'],
                    'mass_kg' => $massKg,
                    'reference_percentage' => $referencePercentage,
                    'classification' => $classification,
                    'reference_min_percentage' => $referenceMin,
                    'reference_max_percentage' => $referenceMax,
                    'reference_version' => ScaleModel::BODYSCAN_REFERENCE_VERSION,
                    'raw_value' => [
                        'label' => $raw['label'] ?? null,
                        'mass_kg' => $raw['mass_kg'] ?? null,
                        'reference_percentage' => $raw['reference_percentage'] ?? null,
                        'classification' => $rawClassification,
                        'text' => $raw['raw_value'] ?? null,
                        'legibility' => $legibility,
                    ],
                ];
            });
        })->values()->all();
    }

    private function sideConsistency(mixed $raw, $suspiciousValues): array
    {
        $raw = is_array($raw) ? $raw : [];
        $leftMarkerVisible = (bool) ($raw['left_marker_visible'] ?? false);
        $rightMarkerVisible = (bool) ($raw['right_marker_visible'] ?? false);
        $possibleSwap = (bool) ($raw['possible_swap'] ?? false) || ! $leftMarkerVisible || ! $rightMarkerVisible;
        $reason = $this->normalizeText($raw['reason'] ?? null);

        if ($possibleSwap) {
            $suspiciousValues->push([
                'field' => 'segmental.side_consistency',
                'value' => null,
                'reason' => $reason ?? 'Não foi possível confirmar com segurança os marcadores esquerdo e direito.',
            ]);
        }

        return [
            'left_marker_visible' => $leftMarkerVisible,
            'right_marker_visible' => $rightMarkerVisible,
            'possible_swap' => $possibleSwap,
            'reason' => $reason,
        ];
    }

    private function appendConsistencyWarnings(array $metrics, array $segments, $suspiciousValues, array &$fieldStatuses): void
    {
        $weight = $metrics['weight_kg'] ?? null;
        $ratioChecks = [
            'body_fat_percentage' => ['mass' => 'fat_mass_kg', 'label' => 'massa gorda'],
            'muscle_rate_percentage' => ['mass' => 'muscle_mass_kg', 'label' => 'massa muscular'],
            'body_water_percentage' => ['mass' => 'water_weight_kg', 'label' => 'peso da água'],
            'protein_percentage' => ['mass' => 'protein_mass_kg', 'label' => 'massa de proteína'],
        ];
        if (is_numeric($weight) && (float) $weight > 0) {
            foreach ($ratioChecks as $percentageField => $check) {
                $percentage = $metrics[$percentageField] ?? null;
                $mass = $metrics[$check['mass']] ?? null;
                if (! is_numeric($percentage) || ! is_numeric($mass)) {
                    continue;
                }

                $calculated = ((float) $mass / (float) $weight) * 100;
                if (abs($calculated - (float) $percentage) > 1.0) {
                    $fieldStatuses['metrics.'.$percentageField] = 'suspicious';
                    $suspiciousValues->push([
                        'field' => 'metrics.'.$percentageField,
                        'value' => $percentage,
                        'reason' => 'O percentual não é coerente com peso e '.$check['label'].'. Confira os valores na imagem.',
                    ]);
                }
            }
        }

        $leanMass = $metrics['lean_body_mass_kg'] ?? null;
        $fatMass = $metrics['fat_mass_kg'] ?? null;
        if (is_numeric($weight) && is_numeric($leanMass) && is_numeric($fatMass)
            && abs(((float) $leanMass + (float) $fatMass) - (float) $weight) > 1.0) {
            $suspiciousValues->push([
                'field' => 'metrics.body_composition_total',
                'value' => (float) $leanMass + (float) $fatMass,
                'reason' => 'A soma de massa magra e massa gorda difere do peso total. Confira os três valores.',
            ]);
        }

        $segmentalFatMass = collect($segments)
            ->where('type', Segment::TYPE_FAT)
            ->sum(fn (array $segment) => is_numeric($segment['mass_kg'] ?? null) ? (float) $segment['mass_kg'] : 0.0);
        if (is_numeric($fatMass) && $segmentalFatMass > 0) {
            $tolerance = max(1.5, (float) $fatMass * 0.05);
            if (abs($segmentalFatMass - (float) $fatMass) > $tolerance) {
                $suspiciousValues->push([
                    'field' => 'segmental.fat.total_mass',
                    'value' => round($segmentalFatMass, 2),
                    'reason' => 'A soma da gordura segmentar difere da massa gorda total além da tolerância de leitura. Confira especialmente o tronco e as pernas.',
                ]);
            }
        }

        foreach ($segments as $segment) {
            $percentage = $segment['reference_percentage'] ?? null;
            $minimum = $segment['reference_min_percentage'] ?? null;
            $maximum = $segment['reference_max_percentage'] ?? null;
            if (! is_numeric($percentage) || ! is_numeric($minimum) || ! is_numeric($maximum)) {
                continue;
            }

            $expectedClassification = (float) $percentage < (float) $minimum
                ? Segment::CLASSIFICATION_LOW
                : ((float) $percentage > (float) $maximum ? Segment::CLASSIFICATION_HIGH : Segment::CLASSIFICATION_STANDARD);
            if (($segment['classification'] ?? null) !== $expectedClassification) {
                $key = 'segmental.'.$segment['type'].'.'.$segment['region'].'.'.$segment['side'].'.classification';
                $fieldStatuses[$key] = 'suspicious';
                $suspiciousValues->push([
                    'field' => $key,
                    'value' => $segment['classification'] ?? null,
                    'reason' => 'A classificação não corresponde ao percentual e à faixa de referência informados.',
                ]);
            }
        }
    }

    private function isExpectedSegment(?string $region, ?string $side): bool
    {
        return collect(Segment::EXPECTED_SEGMENTS)->contains(
            fn (array $segment) => $segment['region'] === $region && $segment['side'] === $side,
        );
    }

    private function normalizeClassification(?string $classification): ?string
    {
        return match ($this->normalizedLabel($classification ?? '')) {
            'baixo', 'baixa', 'low' => Segment::CLASSIFICATION_LOW,
            'padrao', 'normal', 'standard' => Segment::CLASSIFICATION_STANDARD,
            'alto', 'alta', 'high' => Segment::CLASSIFICATION_HIGH,
            default => null,
        };
    }

    private function normalizeMeasuredAt(mixed $value, $suspiciousValues, array &$fieldStatuses): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            $fieldStatuses['measured_at'] = 'missing';

            return null;
        }

        try {
            $measuredAt = Carbon::parse($value, config('app.timezone'));
            $fieldStatuses['measured_at'] = $measuredAt->isFuture() ? 'suspicious' : 'recognized';
            if ($measuredAt->isFuture()) {
                $suspiciousValues->push(['field' => 'measured_at', 'value' => $value, 'reason' => 'A data extraída está no futuro.']);
            }

            return $measuredAt->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            $fieldStatuses['measured_at'] = 'unrecognized';
            $suspiciousValues->push(['field' => 'measured_at', 'value' => $value, 'reason' => 'A data e hora não puderam ser interpretadas.']);

            return null;
        }
    }

    private function normalizeNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        if (! is_string($value) || trim($value) === '') {
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
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 160) : null;
    }

    private function normalizedLabel(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    private function normalizeSourceApplication(mixed $value): ?string
    {
        $normalized = $this->normalizedLabel((string) $value);

        return match (true) {
            str_contains($normalized, 'relaxfit') => ScaleModel::BODYSCAN_APPLICATION_RELAXFIT,
            str_contains($normalized, 'fitdays') => ScaleModel::BODYSCAN_APPLICATION_FITDAYS,
            default => null,
        };
    }

    private function normalizeWeightUnit(mixed $value): ?string
    {
        $normalized = $this->normalizedLabel((string) $value);

        return match (true) {
            $normalized === 'kg', str_contains($normalized, 'quilograma'), str_contains($normalized, 'kilogram') => 'kg',
            in_array($normalized, ['lb', 'lbs'], true), str_contains($normalized, 'libra'), str_contains($normalized, 'pound') => 'lb',
            in_array($normalized, ['st', 'stone', 'stones'], true) => 'stone',
            default => null,
        };
    }

    private function isVisualQualityIssue(string $issue): bool
    {
        $normalized = $this->normalizedLabel($issue);

        return collect([
            'resolucao', 'borrad', 'desfoc', 'ilegivel', 'cortad', 'recort', 'ocult',
            'baixa qualidade', 'contraste', 'reflexo', 'luminos', 'imagem parcial', 'texto pequeno',
        ])->contains(fn (string $term) => str_contains($normalized, $term));
    }

    private function isReadingUncertainty(string $reason): bool
    {
        $normalized = $this->normalizedLabel($reason);

        return collect([
            'ilegivel', 'incert', 'ambigu', 'baixa confianca', 'cortad', 'borrad', 'desfoc',
            'unidade inesperada', 'unidade ausente', 'texto ocult', 'marcador', 'possivel troca',
            'nao reconhecid', 'nao foi possivel',
        ])->contains(fn (string $term) => str_contains($normalized, $term));
    }

    private function signMetadata(array $metadata): string
    {
        $payload = collect([
            'type', 'name', 'size_bytes', 'mime_type', 'stored', 'sha256', 'processed_at',
            'processor_model', 'reference_version', 'measured_at', 'metrics', 'segmental_measurements',
            'raw_values', 'detected_sections', 'field_statuses', 'missing_fields', 'suspicious_values',
            'quality_issues', 'side_consistency', 'source_application', 'report_layout',
            'layout_profile_version', 'layout_evidence', 'available_metric_fields', 'weight_unit',
        ])->mapWithKeys(fn (string $key) => [$key => $metadata[$key] ?? null])->all();

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) config('app.key'));
    }

    private function prompt(): string
    {
        $commonInstructions = <<<'PROMPT'
Analise exclusivamente a imagem anexada. Ela deve ser um relatório da balança Bodyscan Pro exibido pelo aplicativo RelaxFit ou Fitdays.
Identifique source_application pela marca visível no relatório: relaxfit ou fitdays. Use null quando a origem não estiver legível; não deduza pela aparência.
Identifique report_layout exclusivamente por textos realmente visíveis, sem usar posição, cores ou proporções da página:
- fitdays_technical_report exige a marca Fitdays e os textos "Relatório de análise de composição corporal", "Impedância bioelétrica" e "Pontuação corporal";
- fitdays_vertical_report exige a marca Fitdays, os cabeçalhos "Indicador", "Valor" e "Padrão", além dos textos "Limb Dados" e "Controle de peso";
- relaxfit_vertical_report exige a marca RelaxFit, "Análise de gordura segmentar", "Equilíbrio muscular" e ao menos "Dados de membro" ou "Índice corporal".
Em layout_evidence, copie separadamente e sem parafrasear cada marca, título ou cabeçalho usado para identificar o layout. Não inclua evidência que não esteja legível na imagem.
Identifique weight_unit pela unidade impressa ao lado do peso: kg, lb ou stone. Use null se a unidade não estiver legível. Não converta valores entre unidades.
Reconheça o modelo pelo conjunto de textos e seções do layout identificado, nunca apenas pela aparência.
Extraia cada valor pelo rótulo textual e pelo marcador L/R correspondente. Nunca determine o lado apenas pela posição na tela.
Para os segmentos, use somente estes identificadores: type fat ou muscle; region arm, trunk ou leg; side left, center ou right. O tronco deve usar side center.
Devem existir cinco segmentos de gordura e cinco de músculo: braço esquerdo, braço direito, tronco, perna esquerda e perna direita.
Em cada segmento extraia massa em kg, percentual relativo à referência, classificação, limites de referência impressos e o texto bruto visível.
Classificação deve preservar o texto impresso, como Baixo, Padrão ou Alto. Não invente classificação ausente.
Os percentuais segmentares são índices relativos à referência, não percentuais de gordura corporal.
Extraia as métricas gerais pelos nomes dos rótulos: no RelaxFit use o bloco "Índice corporal"; no Fitdays vertical use a tabela "Indicador / Valor / Padrão"; no Fitdays técnico use "Análise da composição corporal" e "Outros indicadores".
Use exclusivamente os aliases do perfil identificado. Para cada métrica, retorne em metric_source_labels o rótulo exato encontrado e em metric_raw_values o texto integral da linha ou célula que sustenta o valor.
Para campos que o perfil não oferece, retorne null em metrics, metric_source_labels e metric_raw_values. Não marque esses campos como ilegíveis ou ausentes.
Preserve exatamente o valor decimal impresso. Não acrescente casas decimais nem alegue margem de erro ou precisão que não esteja escrita no relatório.
Não calcule, estime, deduza ou complete valores ausentes. Use null quando algo não estiver legível.
Converta a data e hora para YYYY-MM-DDTHH:MM:SS sem inventar fuso horário.
Em detected_sections, copie os títulos realmente visíveis.
Marque recognized=false para aplicativo diferente de RelaxFit/Fitdays, relatório sem análise segmentar, evidências insuficientes para um dos três layouts ou imagem que não permita identificar o Bodyscan Pro.
Marque is_complete=false quando houver corte, desfoque, valor ilegível, seção ausente ou lado não confirmável.
Em side_consistency, confirme a visibilidade dos marcadores L e R e marque possible_swap=true diante de qualquer dúvida sobre os lados.
Em legibility use clear, uncertain ou unreadable.
Use quality_issues exclusivamente para problemas da imagem: corte, desfoque, baixa resolução, reflexo, contraste ou texto ilegível.
Use suspicious_values exclusivamente quando o texto, número, unidade ou lado não puder ser lido com confiança.
Pontos, cores e classificações exibidos pelo aplicativo pertencem ao resultado do equipamento. Não os trate como problema de qualidade, valor suspeito, risco clínico, atenção ou saída de faixa.
Não interprete vermelho, amarelo, verde ou azul. Não conclua risco, normalidade ou gravidade a partir de cores.
Não inclua em quality_issues ou suspicious_values valores legíveis apenas por estarem coloridos ou classificados pelo dispositivo.
Liste em português somente problemas de leitura, sem realizar interpretação clínica.
PROMPT;

        return $commonInstructions."\n\nPERFIS OCR SUPORTADOS\n".$this->ocrProfiles->promptBlocks();
    }

    private function responseSchema(): array
    {
        $nullableNumber = ['type' => ['number', 'null']];
        $nullableString = ['type' => ['string', 'null']];
        $metricProperties = [];
        foreach (self::METRIC_FIELDS as $field) {
            $metricProperties[$field] = array_key_exists($field, self::NUMERIC_RANGES) ? $nullableNumber : $nullableString;
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['recognized', 'is_complete', 'quality_issues', 'source_application', 'report_layout', 'layout_evidence', 'weight_unit', 'measured_at', 'detected_sections', 'metrics', 'metric_source_labels', 'metric_raw_values', 'segmental_measurements', 'side_consistency', 'suspicious_values'],
            'properties' => [
                'recognized' => ['type' => 'boolean'],
                'is_complete' => ['type' => 'boolean'],
                'quality_issues' => ['type' => 'array', 'items' => ['type' => 'string']],
                'source_application' => ['type' => ['string', 'null'], 'enum' => ['relaxfit', 'fitdays', null]],
                'report_layout' => ['type' => ['string', 'null'], 'enum' => [...BodyscanReportLayout::LAYOUTS, null]],
                'layout_evidence' => ['type' => 'array', 'items' => ['type' => 'string']],
                'weight_unit' => ['type' => ['string', 'null'], 'enum' => ['kg', 'lb', 'stone', null]],
                'measured_at' => $nullableString,
                'detected_sections' => ['type' => 'array', 'items' => ['type' => 'string']],
                'metrics' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => self::METRIC_FIELDS,
                    'properties' => $metricProperties,
                ],
                'metric_source_labels' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => self::METRIC_FIELDS,
                    'properties' => array_fill_keys(self::METRIC_FIELDS, $nullableString),
                ],
                'metric_raw_values' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => self::METRIC_FIELDS,
                    'properties' => array_fill_keys(self::METRIC_FIELDS, $nullableString),
                ],
                'segmental_measurements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'region', 'side', 'label', 'mass_kg', 'reference_percentage', 'classification', 'reference_min_percentage', 'reference_max_percentage', 'raw_value', 'legibility'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => Segment::TYPES],
                            'region' => ['type' => 'string', 'enum' => Segment::REGIONS],
                            'side' => ['type' => 'string', 'enum' => Segment::SIDES],
                            'label' => $nullableString,
                            'mass_kg' => $nullableNumber,
                            'reference_percentage' => $nullableNumber,
                            'classification' => $nullableString,
                            'reference_min_percentage' => $nullableNumber,
                            'reference_max_percentage' => $nullableNumber,
                            'raw_value' => $nullableString,
                            'legibility' => ['type' => 'string', 'enum' => ['clear', 'uncertain', 'unreadable']],
                        ],
                    ],
                ],
                'side_consistency' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['left_marker_visible', 'right_marker_visible', 'possible_swap', 'reason'],
                    'properties' => [
                        'left_marker_visible' => ['type' => 'boolean'],
                        'right_marker_visible' => ['type' => 'boolean'],
                        'possible_swap' => ['type' => 'boolean'],
                        'reason' => $nullableString,
                    ],
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
