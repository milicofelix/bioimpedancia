<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisRequest;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisSource;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceKnowledgeChunk;
use App\Models\Bioimpedance\BioimpedanceKnowledgeDocument;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BioimpedanceCareGuidanceAssistant
{
    public const PROMPT_VERSION = 'care-guidance-1.0.0';

    private const SOURCE_DEFINITIONS = [
        [
            'title' => 'Guia Alimentar para a População Brasileira',
            'version' => '2ª edição',
            'source_url' => 'https://www.gov.br/saude/pt-br/composicao/saps/promocao-da-saude/guias-alimentares',
            'chunks' => [
                [
                    'chunk_key' => 'brazil-food-guide-natural-foods',
                    'section' => 'Alimentos in natura e minimamente processados',
                    'tags' => ['food', 'general_guidance'],
                    'content' => 'O Guia Alimentar recomenda fazer de alimentos in natura ou minimamente processados, em grande variedade e predominantemente de origem vegetal, a base da alimentação, incluindo frutas, legumes, verduras, feijões e outros alimentos culturalmente apropriados.',
                ],
                [
                    'chunk_key' => 'brazil-food-guide-ultraprocessed',
                    'section' => 'Alimentos ultraprocessados',
                    'tags' => ['food', 'general_guidance'],
                    'content' => 'O Guia Alimentar recomenda evitar alimentos ultraprocessados e favorecer refeições preparadas com alimentos in natura ou minimamente processados.',
                ],
            ],
        ],
        [
            'title' => 'Diretrizes da OMS sobre atividade física e comportamento sedentário',
            'version' => '2020',
            'source_url' => 'https://www.who.int/publications/i/item/9789240015128',
            'chunks' => [
                [
                    'chunk_key' => 'who-adult-physical-activity',
                    'section' => 'Recomendações para adultos',
                    'tags' => ['physical_activity', 'general_guidance'],
                    'content' => 'Para adultos, a OMS recomenda 150 a 300 minutos semanais de atividade aeróbica moderada, ou 75 a 150 minutos de atividade vigorosa, além de fortalecimento dos principais grupos musculares em dois ou mais dias por semana. A progressão deve respeitar capacidade, condições clínicas e orientação profissional.',
                ],
            ],
        ],
        [
            'title' => 'Política de segurança das orientações Ricosty',
            'version' => '1.0.0',
            'source_url' => null,
            'chunks' => [
                [
                    'chunk_key' => 'ricosty-care-guidance-safety',
                    'section' => 'Limites clínicos',
                    'tags' => ['safety', 'prohibited_content'],
                    'content' => 'Não diagnosticar doenças, interpretar exames não registrados, prescrever dieta individualizada, exercício, medicamento, vitamina ou suplemento. Recomendações devem ser gerais, condicionadas à avaliação de profissional habilitado e baseadas somente nos dados disponíveis.',
                ],
            ],
        ],
    ];

    public function suggest(BioimpedanceAssessment $assessment, User $requestedBy): array
    {
        $assessment->loadMissing('client');
        $chunks = $this->approvedChunks($requestedBy);
        $context = $this->context($assessment);
        $request = BioimpedanceAiAnalysisRequest::query()->create([
            'bioimpedance_assessment_id' => $assessment->id,
            'requested_by_user_id' => $requestedBy->id,
            'prompt_version' => self::PROMPT_VERSION,
            'reference_version' => $assessment->reference_version,
            'model_name' => $this->modelName(),
            'status' => 'processing',
            'purpose' => 'care_guidance',
            'structured_context' => $context,
        ]);

        foreach ($chunks as $index => $chunk) {
            BioimpedanceAiAnalysisSource::query()->create([
                'bioimpedance_ai_analysis_request_id' => $request->id,
                'bioimpedance_knowledge_chunk_id' => $chunk->id,
                'relevance_score' => 100 - ($index * 5),
            ]);
        }

        $response = $this->generate($context, $chunks);
        $validationErrors = $this->validate($response, $chunks);
        if ($validationErrors) {
            $response = $this->localResponse($context);
            $validationErrors = $this->validate($response, $chunks);
        }

        $status = $validationErrors ? 'blocked' : 'generated';
        $request->update(['status' => $status, 'validation_errors' => $validationErrors]);
        $output = BioimpedanceAiAnalysisOutput::query()->create([
            'bioimpedance_ai_analysis_request_id' => $request->id,
            'raw_response' => $response,
            'care_guidance' => $status === 'generated' ? $response['care_guidance'] : null,
            'validation_status' => $validationErrors ? 'failed' : 'passed',
        ]);

        return [
            'status' => $status,
            'summary' => $status === 'generated' ? 'Orientações gerais geradas para revisão profissional.' : 'Não foi possível gerar orientações seguras.',
            'suggestion' => $output->care_guidance,
            'output_id' => $output->id,
            'validation_errors' => $validationErrors,
            'sources' => $chunks->map(fn ($chunk) => [
                'id' => $chunk->chunk_key,
                'document' => $chunk->document?->title,
                'section' => $chunk->section,
                'url' => $chunk->document?->source_url,
            ])->values()->all(),
            'notice' => 'Orientação educativa, sem diagnóstico ou prescrição. Exige aprovação do profissional.',
        ];
    }

    private function generate(array $context, Collection $chunks): array
    {
        if (! filled(config('services.openai.api_key')) || config('bioimpedance.assistant.provider') === 'local_reference_engine') {
            return $this->localResponse($context);
        }

        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->acceptJson()->asJson()->timeout((int) config('services.openai.timeout', 45))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                    'model' => config('services.openai.model', 'gpt-5.1'),
                    'input' => [
                        ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $this->systemPrompt()]]],
                        ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => json_encode([
                            'contexto' => $context,
                            'fontes' => $chunks->map(fn ($chunk) => ['id' => $chunk->chunk_key, 'conteudo' => $chunk->content])->values()->all(),
                        ], JSON_UNESCAPED_UNICODE)]]],
                    ],
                    'text' => ['format' => ['type' => 'json_schema', 'name' => 'care_guidance', 'strict' => true, 'schema' => $this->schema()]],
                ])->throw()->json();

            $payload = json_decode($this->outputText($response), true);
            return is_array($payload) ? $payload : $this->localResponse($context);
        } catch (ConnectionException|RequestException) {
            return $this->localResponse($context);
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Você é o agente de orientações educativas da Ricosty. Escreva em português do Brasil para o cliente, usando apenas o contexto e as fontes fornecidas. Relacione os hábitos somente aos pontos de atenção realmente presentes, sem inventar doença, exame, colesterol, deficiência vitamínica ou contraindicação. Não diagnostique nem prescreva dieta, exercício, medicamento, vitamina ou suplemento. Ofereça opções gerais, realistas e graduais de alimentação e movimento, sempre condicionando atividade física à capacidade e eventual liberação profissional. Inclua recomendação de procurar médico ou nutricionista quando apropriado. Não prometa resultados. Informe em source_chunk_ids os IDs que sustentam o texto. O texto final deve ser acolhedor, objetivo e adequado ao relatório. Responda apenas no schema solicitado.
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['care_guidance', 'source_chunk_ids'],
            'properties' => [
                'care_guidance' => ['type' => 'string'],
                'source_chunk_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    private function localResponse(array $context): array
    {
        $attention = collect($context['attention_points'])->implode(', ');
        $opening = $attention
            ? 'Os pontos que merecem atenção nesta avaliação são: '.$attention.'. As sugestões abaixo são gerais e devem ser adaptadas pelo profissional à sua realidade.'
            : 'A avaliação não apresentou ponto específico que justifique uma conduta individualizada. As sugestões abaixo apoiam a manutenção de hábitos saudáveis.';

        return [
            'care_guidance' => $opening."\n\nAlimentação: priorize refeições variadas baseadas em alimentos in natura ou minimamente processados, como frutas, legumes, verduras e feijões. Reduza a frequência de ultraprocessados, bebidas açucaradas e lanches industrializados. Vitaminas e suplementos só devem ser usados quando indicados por profissional habilitado após avaliação adequada.\n\nMovimento: se estiver apto e liberado para atividade física, aumente o movimento de forma gradual. Caminhada, bicicleta, dança e exercícios de fortalecimento são opções possíveis. Como referência geral para adultos, a OMS recomenda de 150 a 300 minutos semanais de atividade moderada e fortalecimento muscular em dois ou mais dias, com adaptação à condição individual.\n\nAcompanhamento: defina metas pequenas e possíveis, acompanhe a evolução em condições semelhantes de medição e procure médico, nutricionista ou profissional de educação física para orientação individualizada.",
            'source_chunk_ids' => ['brazil-food-guide-natural-foods', 'brazil-food-guide-ultraprocessed', 'who-adult-physical-activity', 'ricosty-care-guidance-safety'],
        ];
    }

    private function validate(array $response, Collection $chunks): array
    {
        $errors = [];
        $text = Str::lower((string) ($response['care_guidance'] ?? ''));
        if (mb_strlen($text) < 100 || mb_strlen($text) > 4000) $errors[] = 'Orientação fora do tamanho permitido.';
        foreach (['diagnóstico de', 'prescrevo', 'tome ', 'dose de', 'mg por', 'colesterol alto', 'diabetes', 'hipertensão'] as $forbidden) {
            if (str_contains($text, $forbidden)) $errors[] = 'Conteúdo clínico não sustentado detectado.';
        }
        $allowed = $chunks->pluck('chunk_key')->all();
        foreach ($response['source_chunk_ids'] ?? [] as $key) {
            if (! in_array($key, $allowed, true)) $errors[] = 'Fonte não aprovada informada.';
        }
        if (empty($response['source_chunk_ids'])) $errors[] = 'Orientação sem fontes.';

        return array_values(array_unique($errors));
    }

    private function context(BioimpedanceAssessment $assessment): array
    {
        $indicators = $assessment->analysis['indicators'] ?? [];
        $labels = ['bmi' => 'IMC', 'body_fat' => 'gordura corporal', 'skeletal_muscle' => 'músculo esquelético', 'visceral_fat' => 'gordura visceral'];
        $attention = collect($labels)->map(function ($label, $key) use ($indicators) {
            $indicator = $indicators[$key] ?? [];
            return in_array($indicator['tone'] ?? null, ['warning', 'danger', 'attention'], true) && filled($indicator['classification'] ?? null)
                ? $label.' '.mb_strtolower($indicator['classification']) : null;
        })->filter()->values()->all();

        return [
            'assessment_id' => $assessment->id,
            'assessment_updated_at' => $assessment->updated_at?->toIso8601String(),
            'age_at_assessment' => $assessment->age_at_assessment,
            'professional_observation' => $assessment->notes,
            'attention_points' => $attention,
            'available_data_notice' => 'A avaliação contém composição corporal; não contém exames laboratoriais, diagnósticos, medicações, alergias ou deficiências vitamínicas.',
        ];
    }

    private function approvedChunks(User $user): Collection
    {
        foreach (self::SOURCE_DEFINITIONS as $definition) {
            $document = BioimpedanceKnowledgeDocument::query()->updateOrCreate(
                ['title' => $definition['title'], 'version' => $definition['version']],
                ['document_type' => 'clinical_reference', 'source_url' => $definition['source_url'], 'approved' => true, 'approved_by_user_id' => $user->id, 'approved_at' => now()]
            );
            foreach ($definition['chunks'] as $chunk) {
                BioimpedanceKnowledgeChunk::query()->updateOrCreate(['chunk_key' => $chunk['chunk_key']], [
                    'bioimpedance_knowledge_document_id' => $document->id,
                    'section' => $chunk['section'], 'language' => 'pt-BR', 'tags' => $chunk['tags'],
                    'content' => $chunk['content'], 'checksum' => hash('sha256', $chunk['content']),
                ]);
            }
        }

        $keys = collect(self::SOURCE_DEFINITIONS)->pluck('chunks')->flatten(1)->pluck('chunk_key');
        return BioimpedanceKnowledgeChunk::query()->with('document')->whereIn('chunk_key', $keys)->get();
    }

    private function modelName(): string
    {
        return filled(config('services.openai.api_key')) ? (string) config('services.openai.model', 'gpt-5.1') : 'local-care-guidance-agent';
    }

    private function outputText(array $response): string
    {
        if (filled($response['output_text'] ?? null)) return (string) $response['output_text'];
        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') return (string) ($content['text'] ?? '');
            }
        }
        return '';
    }
}
