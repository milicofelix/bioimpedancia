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
        if (! $this->needsCompletion($segments)) {
            return $segments;
        }

        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.openai.timeout', 45))
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
            return $segments;
        }

        $payload = json_decode($this->outputText($response), true);
        $secondReading = is_array($payload['segmental_measurements'] ?? null)
            ? $payload['segmental_measurements']
            : [];

        return $this->merge($segments, $secondReading);
    }

    private function needsCompletion(array $segments): bool
    {
        if (count($segments) !== 10) {
            return true;
        }

        return collect($segments)->contains(fn ($segment) => ! is_array($segment)
            || collect(['mass_kg', 'reference_percentage', 'classification'])->contains(
                fn (string $field) => ! isset($segment[$field]) || $segment[$field] === ''
            )
            || ($segment['legibility'] ?? null) !== 'clear');
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
                    return $original;
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

    private function key(array $segment): string
    {
        return ($segment['type'] ?? '').'|'.($segment['region'] ?? '').'|'.($segment['side'] ?? '');
    }

    private function prompt(array $firstReading): string
    {
        return <<<'PROMPT'
Faça uma segunda leitura independente, focada exclusivamente nas seções "Análise de gordura segmentar" e "Equilíbrio muscular" do relatório Bodyscan Pro/RelaxFit.
O relatório é vertical e os valores são pequenos. Amplie visualmente cada uma dessas duas seções antes de transcrever.
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
