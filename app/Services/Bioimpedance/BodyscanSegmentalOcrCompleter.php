<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceSegmentalMeasurement as Segment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class BodyscanSegmentalOcrCompleter
{
    public function complete(string $imageContents, string $model, array $segments): array
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
                            ['type' => 'input_text', 'text' => $this->prompt($segments)],
                            ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,'.base64_encode($imageContents), 'detail' => 'high'],
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
                        'legibility' => 'uncertain',
                        'raw_value' => ($original['raw_value'] ?? 'Leitura inicial disponível').' | Segunda leitura indisponível',
                    ];
                }

                $conflictingFields = collect(['mass_kg', 'reference_percentage', 'classification'])
                    ->filter(fn (string $field) => $this->valuesConflict($field, $original[$field] ?? null, $retry[$field] ?? null))
                    ->values();
                if ($conflictingFields->isNotEmpty()) {
                    $merged = $original;
                    foreach ($conflictingFields as $field) {
                        $merged[$field] = null;
                    }
                    $merged['legibility'] = 'uncertain';
                    $merged['raw_value'] = 'Leitura inicial: '.($original['raw_value'] ?? 'não informada')
                        .' | Segunda leitura: '.($retry['raw_value'] ?? 'não informada');

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
                'legibility' => 'uncertain',
                'raw_value' => ($segment['raw_value'] ?? 'Leitura inicial disponível').' | Segunda leitura indisponível',
            ];
        })->all();
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

    private function prompt(array $firstReading): string
    {
        return <<<'PROMPT'
Faça uma segunda leitura independente, focada exclusivamente nas seções "Análise de gordura segmentar" e "Equilíbrio muscular" do relatório Bodyscan Pro exibido pelo RelaxFit ou Fitdays.
O relatório pode ser vertical ou técnico em página. Amplie visualmente cada uma dessas duas seções antes de transcrever.
Em cada seção há cinco regiões: braço esquerdo, braço direito, tronco, perna esquerda e perna direita.
Para cada região, leia na ordem apresentada: massa em kg, percentual relativo à referência e classificação textual.
Não confunda percentual de referência com massa. Não transforme 3.7 kg em 37 e não remova o primeiro dígito de percentuais como 397.8% ou 97.4%.
Use os marcadores L e R escritos na imagem; não determine o lado somente pela posição visual.
As faixas de referência impressas no cabeçalho da seção podem ser aplicadas aos cinco segmentos daquela mesma seção, mas nunca entre seções diferentes.
Classificação deve ser apenas low, standard ou high conforme o texto Baixo, Padrão ou Alto realmente visível.
Ignore cores como fonte de classificação quando o texto não estiver legível.
Use null para qualquer valor que continuar ilegível. Não calcule massas, percentuais ou classificações.
Marque legibility como clear somente quando massa, percentual e classificação estiverem efetivamente legíveis.
Retorne exatamente dez segmentos, sem métricas gerais.
PROMPT
        ."\n\nPrimeira leitura, fornecida apenas para indicar os pontos que precisam ser relidos; não a trate como fonte confiável:\n"
        .json_encode($firstReading, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
