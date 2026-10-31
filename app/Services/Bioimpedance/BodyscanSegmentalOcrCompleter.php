<?php

namespace App\Services\Bioimpedance;

use App\Domain\Bioimpedance\BodyscanOcrProfileRegistry;
use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement as Segment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class BodyscanSegmentalOcrCompleter
{
    public function __construct(
        private readonly BodyscanOcrProfileRegistry $ocrProfiles,
        private readonly BodyscanSegmentalImagePreparer $imagePreparer,
    ) {}

    public function complete(string $imageContents, string $model, array $segments, ?string $reportLayout = null): array
    {
        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout((int) config('services.openai.bodyscan_ocr_timeout', 90))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                    'model' => $model,
                    'store' => false,
                    'max_output_tokens' => 4200,
                    'input' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => $this->prompt($segments, $reportLayout)],
                            ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,'.base64_encode($imageContents), 'detail' => 'high'],
                            ...$this->preparedImageContent($imageContents, $reportLayout),
                        ],
                    ]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'bodyscan_segmental_completion',
                            'strict' => true,
                            'schema' => $this->schema(),
                        ],
                    ],
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            return $this->markSecondReadingUnavailable($segments);
        }

        $payload = json_decode($this->outputText($response), true);
        $secondReading = is_array($payload['segmental_measurements'] ?? null)
            ? $payload['segmental_measurements']
            : [];

        if ($secondReading === []) {
            return $this->markSecondReadingUnavailable($segments);
        }

        return $this->merge($segments, $secondReading);
    }

    private function merge(array $firstReading, array $secondReading): array
    {
        $firstByKey = collect($firstReading)->filter(fn ($item) => is_array($item))->keyBy(fn (array $item) => $this->key($item));
        $secondByKey = collect($secondReading)->filter(fn ($item) => is_array($item))->keyBy(fn (array $item) => $this->key($item));

        return collect(Segment::TYPES)->flatMap(fn (string $type) => collect(Segment::EXPECTED_SEGMENTS)
            ->map(function (array $expected) use ($type, $firstByKey, $secondByKey) {
                $key = $type.'|'.$expected['region'].'|'.$expected['side'];
                $original = $firstByKey->get($key);
                $retry = $secondByKey->get($key);
                if (! is_array($original)) {
                    return $retry;
                }
                if (! is_array($retry)) {
                    return [
                        ...$original,
                        'raw_value' => ($original['raw_value'] ?? 'Leitura inicial disponível').' | Segunda leitura indisponível',
                    ];
                }

                $conflictingFields = collect(['mass_kg', 'reference_percentage', 'classification'])
                    ->filter(fn (string $field) => $this->valuesConflict($field, $original[$field] ?? null, $retry[$field] ?? null))
                    ->values();
                if ($conflictingFields->isNotEmpty()) {
                    // A segunda passagem trabalha com ampliações exclusivas das
                    // seções segmentares e prevalece quando está integralmente
                    // legível. A divergência permanece no texto bruto para
                    // auditoria, mas só vira pendência se essa releitura também
                    // estiver incerta ou incompleta.
                    $merged = [...$original, ...$retry];
                    $merged['raw_value'] = 'Leitura inicial: '.($original['raw_value'] ?? 'não informada')
                        .' | Segunda leitura: '.($retry['raw_value'] ?? 'não informada');

                    if (! $this->hasClearCoreReading($retry)) {
                        $merged['legibility'] = 'uncertain';
                        $merged['review_reason'] = $this->conflictReason($type, $expected, $conflictingFields->all());
                    }

                    return $merged;
                }

                $merged = $original;
                foreach (['label', 'mass_kg', 'reference_percentage', 'classification', 'reference_min_percentage', 'reference_max_percentage', 'raw_value'] as $field) {
                    if (($merged[$field] ?? null) === null || ($merged[$field] ?? null) === '') {
                        $merged[$field] = $retry[$field] ?? $merged[$field] ?? null;
                    }
                }
                if (($original['legibility'] ?? null) !== 'clear' && ($retry['legibility'] ?? null) === 'clear') {
                    $merged = [...$original, ...$retry];
                }

                return $merged;
            }))->filter()->values()->all();
    }

    private function markSecondReadingUnavailable(array $segments): array
    {
        return collect($segments)->map(function ($segment) {
            if (! is_array($segment)) {
                return $segment;
            }

            return [
                ...$segment,
                'raw_value' => ($segment['raw_value'] ?? 'Leitura inicial disponível').' | Segunda leitura indisponível',
            ];
        })->all();
    }

    private function conflictReason(string $type, array $segment, array $fields): string
    {
        $typeLabel = $type === Segment::TYPE_FAT ? 'Gordura segmentar' : 'Equilíbrio muscular';
        $regionLabel = $this->regionLabel($segment['region'], $segment['side']);
        $fieldLabels = [
            'mass_kg' => 'massa',
            'reference_percentage' => 'percentual de referência',
            'classification' => 'classificação',
        ];
        $labels = collect($fields)->map(fn (string $field) => $fieldLabels[$field] ?? $field)->implode(', ');

        return "{$typeLabel} — {$regionLabel}: as duas leituras divergiram em {$labels}.";
    }

    private function hasClearCoreReading(array $segment): bool
    {
        return ($segment['legibility'] ?? null) === 'clear'
            && is_numeric($segment['mass_kg'] ?? null)
            && is_numeric($segment['reference_percentage'] ?? null)
            && filled($segment['classification'] ?? null);
    }

    private function regionLabel(string $region, string $side): string
    {
        if ($region === Segment::REGION_TRUNK) {
            return 'tronco';
        }

        $regionLabel = $region === Segment::REGION_ARM ? 'braço' : 'perna';
        $sideLabel = match ($side) {
            Segment::SIDE_LEFT => $region === Segment::REGION_LEG ? ' esquerda' : ' esquerdo',
            Segment::SIDE_RIGHT => $region === Segment::REGION_LEG ? ' direita' : ' direito',
            default => '',
        };

        return $regionLabel.$sideLabel;
    }

    private function valuesConflict(string $field, mixed $first, mixed $second): bool
    {
        if ($first === null || $first === '' || $second === null || $second === '') {
            return false;
        }

        if (is_numeric($first) && is_numeric($second)) {
            return abs((float) $first - (float) $second) > 0.01;
        }

        if ($field === 'classification') {
            $labels = [
                'baixo' => 'low', 'baixa' => 'low', 'low' => 'low',
                'padrao' => 'standard', 'normal' => 'standard', 'standard' => 'standard',
                'alto' => 'high', 'alta' => 'high', 'high' => 'high',
            ];
            $normalize = fn (mixed $value) => $labels[mb_strtolower(
                preg_replace('/[^a-z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $value) ?: ''),
            )] ?? null;

            return $normalize($first) !== $normalize($second);
        }

        return mb_strtolower(trim((string) $first)) !== mb_strtolower(trim((string) $second));
    }

    private function key(array $segment): string
    {
        return ($segment['type'] ?? '').'|'.($segment['region'] ?? '').'|'.($segment['side'] ?? '');
    }

    private function prompt(array $firstReading, ?string $reportLayout): string
    {
        $profileContext = '';
        if (is_string($reportLayout) && $reportLayout !== '') {
            try {
                $profileContext = "\n\nPerfil identificado na primeira leitura:\n".$this->ocrProfiles->forLayout($reportLayout)->segmentalPromptBlock();
            } catch (\InvalidArgumentException) {
                $profileContext = '';
            }
        }

        return <<<'PROMPT'
Faça uma segunda leitura independente, focada exclusivamente nas seções "Análise de gordura segmentar" e "Equilíbrio muscular" do relatório Bodyscan Pro exibido pelo RelaxFit ou Fitdays.
O relatório pode ser vertical ou técnico em página. Amplie visualmente cada uma dessas duas seções antes de transcrever.
Em cada seção há cinco regiões: braço esquerdo, braço direito, tronco, perna esquerda e perna direita.
Para cada região, leia na ordem apresentada: massa em kg, percentual relativo à referência e classificação textual.
Não confunda percentual de referência com massa. Não transforme 3.7 kg em 37 e não remova o primeiro dígito de percentuais como 397.8% ou 97.4%.
Use os marcadores L e R escritos na imagem; não determine o lado somente pela posição visual.
No relatório técnico Fitdays, há dois diagramas adjacentes: gordura segmentar à esquerda e equilíbrio muscular à direita. Não misture os valores de um diagrama com o outro.
Associe cada conjunto de três linhas ao marcador impresso: massa em kg, percentual e classificação. O marcador L identifica side left e o marcador R identifica side right. Não espelhe os lados pela perspectiva anatômica.
Na ampliação do relatório técnico, os valores ligados ao marcador L ficam na coluna visual esquerda e os ligados ao marcador R na coluna visual direita; essa regra só vale quando os próprios marcadores L/R estiverem legíveis.
No diagrama técnico, a linha central ligada ao torso é trunk/center. As linhas superiores são arm e as inferiores são leg.
As faixas de referência impressas no cabeçalho da seção podem ser aplicadas aos cinco segmentos daquela mesma seção, mas nunca entre seções diferentes.
Classificação deve ser apenas low, standard ou high conforme o texto Baixo, Padrão ou Alto realmente visível.
Ignore cores como fonte de classificação quando o texto não estiver legível.
Use null para qualquer valor que continuar ilegível. Não calcule massas, percentuais ou classificações.
Marque legibility como clear somente quando massa, percentual e classificação estiverem efetivamente legíveis.
Retorne exatamente dez segmentos, sem métricas gerais.
PROMPT
        .$profileContext
        ."\n\nPrimeira leitura, fornecida apenas para indicar os pontos que precisam ser relidos; não a trate como fonte confiável:\n"
        .json_encode($firstReading, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function preparedImageContent(string $imageContents, ?string $reportLayout): array
    {
        return collect($this->imagePreparer->prepare($imageContents, $reportLayout))
            ->flatMap(fn (array $image) => [
                ['type' => 'input_text', 'text' => $image['label'].':'],
                ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,'.base64_encode($image['contents']), 'detail' => 'high'],
            ])->values()->all();
    }

    private function schema(): array
    {
        $nullableNumber = ['type' => ['number', 'null']];
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['segmental_measurements'],
            'properties' => [
                'segmental_measurements' => [
                    'type' => 'array',
                    'minItems' => 10,
                    'maxItems' => 10,
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
                            'classification' => ['type' => ['string', 'null'], 'enum' => ['low', 'standard', 'high', null]],
                            'reference_min_percentage' => $nullableNumber,
                            'reference_max_percentage' => $nullableNumber,
                            'raw_value' => $nullableString,
                            'legibility' => ['type' => 'string', 'enum' => ['clear', 'uncertain', 'unreadable']],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function outputText(array $response): string
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
