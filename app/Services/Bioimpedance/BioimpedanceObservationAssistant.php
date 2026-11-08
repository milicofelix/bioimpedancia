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

class BioimpedanceObservationAssistant
{
    public const PROMPT_VERSION = 'rag-client-1.6.0';

    public const MODEL_NAME = 'local-validated-rag-agent';

    public function __construct(
        private readonly KnowledgeSourceSnapshot $sourceSnapshot,
        private readonly PublishedKnowledgeSourceRepository $publishedSources,
        private readonly BioimpedanceAssistantContext $assistantContext,
        private readonly AssessmentNarrative $narrative,
        private readonly ClinicalTextGuard $textGuard,
    ) {}

    private const DEFAULT_DOCUMENTS = [
        [
            'title' => 'Manual Omron HBF-514C',
            'document_type' => 'manufacturer_manual',
            'manufacturer' => 'Omron',
            'device_model' => 'HBF-514C',
            'version' => 'LA IM SP r2',
            'source_url' => 'https://omronhealthcare.com/products/body-composition-monitor-and-scale-with-seven-fitness-indicators-hbf-514c',
            'chunks' => [
                [
                    'chunk_key' => 'omron-hbf514c-body-fat',
                    'section' => 'Classificação de gordura corporal',
                    'page' => 27,
                    'tags' => ['body_fat', 'classification', 'HBF-514C'],
                    'content' => 'A gordura corporal deve ser interpretada pelas faixas do manual Omron HBF-514C conforme sexo e idade. A classificação oficial é calculada pelo backend e não deve ser recalculada pelo assistente.',
                ],
                [
                    'chunk_key' => 'omron-hbf514c-skeletal-muscle',
                    'section' => 'Classificação de músculo esquelético',
                    'page' => 27,
                    'tags' => ['skeletal_muscle', 'classification', 'HBF-514C'],
                    'content' => 'O músculo esquelético deve ser interpretado pelas faixas do manual Omron HBF-514C conforme sexo e idade. A classificação oficial é calculada pelo backend e não deve ser substituída.',
                ],
                [
                    'chunk_key' => 'omron-hbf514c-visceral-fat',
                    'section' => 'Classificação de gordura visceral',
                    'page' => 28,
                    'tags' => ['visceral_fat', 'classification', 'HBF-514C'],
                    'content' => 'Na Omron HBF-514C, gordura visceral de 1 a 9 é classificada como normal, de 10 a 14 como elevada e de 15 a 30 como muito elevada.',
                ],
            ],
        ],
        [
            'title' => 'Referência OMS para IMC',
            'document_type' => 'clinical_reference',
            'manufacturer' => null,
            'device_model' => null,
            'version' => 'adult-bmi',
            'source_url' => null,
            'chunks' => [
                [
                    'chunk_key' => 'who-adult-bmi-classification',
                    'section' => 'Classificação adulta de IMC',
                    'page' => null,
                    'tags' => ['bmi', 'classification'],
                    'content' => 'Para adultos, o IMC é classificado em baixo peso, eutrofia, sobrepeso e graus de obesidade. A classificação apresentada ao assistente deve ser a classificação calculada pelo backend.',
                ],
            ],
        ],
        [
            'title' => 'Protocolo de medição Vita Scan',
            'document_type' => 'clinic_protocol',
            'manufacturer' => null,
            'device_model' => null,
            'version' => '1.0.0',
            'source_url' => null,
            'chunks' => [
                [
                    'chunk_key' => 'vita-scan-measurement-protocol',
                    'section' => 'Condições de medição',
                    'page' => null,
                    'tags' => ['measurement_protocol', 'guidance'],
                    'content' => 'As avaliações devem ser comparadas preferencialmente em condições semelhantes de hidratação, alimentação, horário e rotina prévia, pois esses fatores podem influenciar estimativas de bioimpedância.',
                ],
                [
                    'chunk_key' => 'vita-scan-assistant-safety-policy',
                    'section' => 'Política de segurança do assistente',
                    'page' => null,
                    'tags' => ['safety_policy', 'prohibited_content'],
                    'content' => 'O assistente não deve produzir diagnóstico, prescrever dieta, medicamento, suplemento, tratamento ou substituir avaliação médica, nutricional ou de outro profissional habilitado.',
                ],
            ],
        ],
    ];

    private const METRIC_CHUNKS = [
        'bmi' => 'who-adult-bmi-classification',
        'body_fat' => 'omron-hbf514c-body-fat',
        'skeletal_muscle' => 'omron-hbf514c-skeletal-muscle',
        'visceral_fat' => 'omron-hbf514c-visceral-fat',
    ];

    public function suggest(BioimpedanceAssessment $assessment, ?User $requestedBy = null): array
    {
        $assessment->loadMissing(['client', 'professional', 'segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);
        $this->ensureDefaultKnowledgeBase($requestedBy);

        $history = $this->historyBefore($assessment);
        $context = $this->structuredContext($assessment, $history);
        $chunks = $this->retrieveApprovedChunks($context);
        $request = BioimpedanceAiAnalysisRequest::query()->create([
            'bioimpedance_assessment_id' => $assessment->id,
            'requested_by_user_id' => $requestedBy?->id,
            'prompt_version' => self::PROMPT_VERSION,
            'reference_version' => $assessment->reference_version,
            'model_name' => $this->modelName(),
            'status' => 'processing',
            'structured_context' => $context,
        ]);

        foreach ($chunks as $index => $chunk) {
            BioimpedanceAiAnalysisSource::query()->create([
                'bioimpedance_ai_analysis_request_id' => $request->id,
                'bioimpedance_knowledge_chunk_id' => $chunk->id,
                'relevance_score' => 100 - ($index * 5),
                'source_snapshot' => $this->sourceSnapshot->fromChunk($chunk),
            ]);
        }

        $response = $this->generateResponse($assessment, $context, $chunks);
        $response['provider'] ??= 'local_reference_engine';
        $validationErrors = $this->validateResponse($response, $assessment, $chunks, $context);
        $externalResponse = null;
        if (count($validationErrors) && ($response['provider'] ?? null) === 'openai') {
            $externalResponse = $response;
            $openAiValidationErrors = $validationErrors;
            $localResponse = [
                ...$this->generateStructuredResponse($assessment, $context, $chunks),
                'provider' => 'local_reference_engine',
                'fallback_reason' => 'Resposta da OpenAI bloqueada pelo validador; observação segura gerada pelo backend.',
            ];
            $validationErrors = $this->validateResponse($localResponse, $assessment, $chunks, $context);
            $response = [
                ...$localResponse,
                'blocked_provider' => 'openai',
                'blocked_provider_errors' => $openAiValidationErrors,
            ];
        }
        $status = count($validationErrors) ? 'blocked' : $response['status'];
        $response['fallback_used'] = filled($response['fallback_reason'] ?? null);
        $response['attempted_provider'] = $response['provider'] === 'openai' || $response['fallback_used'] ? 'openai' : null;
        $generationAudit = [
            'provider' => $response['provider'],
            'attempted_provider' => $response['attempted_provider'],
            'fallback_used' => $response['fallback_used'],
            'fallback_reason' => $response['fallback_reason'] ?? null,
            'external_validation_errors' => $response['blocked_provider_errors'] ?? [],
            'final_validation_errors' => $validationErrors,
            'external_response' => $externalResponse,
            'attempted_model' => $response['attempted_provider'] ? (string) config('services.openai.model', 'gpt-5.1') : null,
        ];

        $request->update([
            'status' => $status,
            'validation_errors' => $validationErrors,
            'provider' => $response['provider'],
            'attempted_provider' => $response['attempted_provider'],
            'fallback_used' => $response['fallback_used'],
            'fallback_reason' => $response['fallback_reason'] ?? null,
            'model_name' => $response['provider'] === 'openai' ? $this->modelName() : self::MODEL_NAME,
        ]);
        $response['evidence_catalog'] = $context['evidence_catalog'] ?? [];
        $response['findings'] = $context['findings'] ?? [];
        $response['guidance_evidence_map'] = collect(['positive_points', 'attention_points', 'general_guidance'])
            ->flatMap(fn (string $section) => collect($response[$section] ?? [])->map(fn (array $point) => [
                'section' => $section,
                'text' => $point['text'] ?? null,
                'source_ids' => $point['source_chunk_ids'] ?? [],
            ]))->values()->all();

        $output = BioimpedanceAiAnalysisOutput::query()->create([
            'bioimpedance_ai_analysis_request_id' => $request->id,
            'raw_response' => [...$response, 'generation_audit' => $generationAudit],
            'structured_claims' => $status === 'generated' ? ($context['findings'] ?? []) : [],
            'professional_observation' => $status === 'generated' ? $response['professional_observation'] : null,
            'validation_status' => count($validationErrors) ? 'failed' : 'passed',
        ]);

        return [
            ...$response,
            'status' => $status,
            'suggestion' => $output->professional_observation,
            'request_id' => $request->id,
            'output_id' => $output->id,
            'validation_status' => $output->validation_status,
            'validation_errors' => $validationErrors,
            'sources' => $this->sourcesPayload($chunks),
            'context' => [
                'client_name' => $assessment->client?->full_name,
                'age_at_assessment' => $assessment->age_at_assessment,
                'biological_sex_at_assessment' => $assessment->biological_sex_at_assessment,
                'device_model' => $assessment->device_model,
                'reference_version' => $assessment->reference_version,
                'previous_assessments_count' => $history->count(),
                'warnings' => $assessment->analysis['warnings'] ?? [],
            ],
            'references' => $this->sourcesPayload($chunks),
            'notice' => $this->noticeText($response),
        ];
    }

    private function modelName(): string
    {
        return filled(config('services.openai.api_key'))
            ? (string) config('services.openai.model', 'gpt-5.1')
            : self::MODEL_NAME;
    }

    private function generateResponse(BioimpedanceAssessment $assessment, array $context, Collection $chunks): array
    {
        $provider = config('bioimpedance.assistant.provider', 'hybrid');

        if ($provider === 'local_reference_engine') {
            return $this->generateStructuredResponse($assessment, $context, $chunks);
        }

        if (! filled(config('services.openai.api_key'))) {
            if ($provider === 'openai') {
                return [...$this->blockedResponse('O serviço externo não está configurado. Solicite ao administrador a revisão da integração.'), 'provider' => 'openai'];
            }

            return [
                ...$this->generateStructuredResponse($assessment, $context, $chunks),
                'provider' => 'local_reference_engine',
                'fallback_reason' => 'O provedor externo não está configurado.',
            ];
        }

        try {
            return [
                ...$this->generateOpenAiResponse($context, $chunks),
                'provider' => 'openai',
                'fallback_reason' => null,
            ];
        } catch (ConnectionException|RequestException $exception) {
            if ($provider === 'openai') {
                return [...$this->blockedResponse('O provedor externo está indisponível. Tente novamente mais tarde.'), 'provider' => 'openai'];
            }

            return [
                ...$this->generateStructuredResponse($assessment, $context, $chunks),
                'provider' => 'local_reference_engine',
                'fallback_reason' => 'Falha de comunicação com o provedor externo.',
            ];
        }
    }

    private function generateOpenAiResponse(array $context, Collection $chunks): array
    {
        $response = Http::withToken((string) config('services.openai.api_key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) config('services.openai.timeout', 45))
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                'model' => config('services.openai.model', 'gpt-5.1'),
                'store' => false,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $this->openAiSystemPrompt(),
                        ]],
                    ],
                    [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => json_encode([
                                'contexto_estruturado' => $context,
                                'fontes_aprovadas' => $this->sourcesForPrompt($chunks),
                            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        ]],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'bioimpedance_observation_suggestion',
                        'strict' => true,
                        'schema' => $this->openAiResponseSchema(),
                    ],
                ],
            ])
            ->throw()
            ->json();

        $payload = json_decode($this->extractOpenAiText($response), true);
        if (! is_array($payload)) {
            return $this->blockedResponse('A OpenAI retornou uma resposta fora do JSON esperado.');
        }

        return [
            'status' => $payload['status'] ?? 'generated',
            'summary' => $payload['summary'] ?? 'Sugestão gerada pela OpenAI com fontes aprovadas.',
            'positive_points' => $payload['positive_points'] ?? [],
            'attention_points' => $payload['attention_points'] ?? [],
            'general_guidance' => $payload['general_guidance'] ?? [],
            'professional_observation' => $payload['professional_observation'] ?? null,
            'prohibited_content_detected' => (bool) ($payload['prohibited_content_detected'] ?? false),
        ];
    }

    private function openAiSystemPrompt(): string
    {
        return <<<'PROMPT'
Você é um assistente de apoio à avaliação de bioimpedância da Vita Scan.
Gere uma observação final clara, acolhedora e adequada para constar no relatório entregue ao cliente.
A observação será revisada e aprovada pelo profissional antes de entrar no relatório.
Use somente o contexto estruturado e as fontes aprovadas enviadas.
Use findings para escolher os achados mais relevantes. Diferencie valores registrados, classificações do equipamento e classificações calculadas pelo sistema. Explique as limitações dos achados escolhidos, sem listar todos os números nem repetir orientações genéricas. Não infira hábitos, sintomas ou restrições ausentes.
Siga finding_selection_policy. Considere todas as regiões e resuma grupos com a mesma classificação, citando todas as evidências envolvidas; nunca selecione apenas as primeiras posições da lista. Destaque o tronco quando seu percentual relativo à referência se destacar dentro da gordura segmentar, sem interpretar esse percentual como gordura regional ou risco clínico. Não compare percentuais de referência de gordura e músculo como se fossem equivalentes. Mencione a ausência de histórico uma única vez e só quando history_comparison.has_previous for falso. Se houver histórico incompatível, explique a incompatibilidade em vez de chamar a avaliação de inicial. Não enumere WHR, ASMI e idade corporal sem utilidade para a síntese. Limite a observação a 1800 caracteres e priorize interpretação descritiva fundamentada, não um inventário de medidas.
Não altere, recalcule nem questione as classificações oficiais do backend.
Não gere diagnóstico, prescrição, dieta, medicação, suplemento ou tratamento.
Não afirme causalidade médica. Não substitua avaliação de profissional habilitado.
Para dados Relaxmedic, trate composição corporal e idade corporal como estimativas proprietárias, gordura visceral como índice do dispositivo e TMB como estimativa que não equivale ao gasto energético total. Não aplique faixas Omron nem conclua que força ou função muscular estão preservadas.
Para dados Bodyscan, não conclua perda muscular, assimetria clínica ou preservação funcional com base nos segmentos. Descreva somente valores e tendências, ressalvando condições de medição e necessidade de avaliação profissional.
Use redação natural para grupos de regiões, sem frases telegráficas como "Gordura segmentar: Alto em 5 regiões". Preserve a classificação literal do equipamento e diferencie percentual relativo à referência de percentual de gordura local. Quando measurement_profile.athlete_mode for verdadeiro, informe uma única vez que o perfil Atleta foi registrado e que sua adequação deve ser confirmada com o profissional, pois influencia as estimativas; não afirme que ele é adequado nem que o cliente é atleta.
WHR e ASMI são índices estimados pelo equipamento: não diagnostique risco, sarcopenia ou qualquer condição a partir deles. Nunca trate idade corporal como idade biológica real, peso-alvo do aplicativo como peso ideal prescritivo ou pontuação corporal como nota clínica.
Considere o aplicativo, o layout e o modo atleta registrados. Quando o histórico indicar mudança entre modo atleta e padrão ou perfis incompatíveis, informe a incompatibilidade e não descreva a diferença como melhora ou piora.
Cada afirmação baseada na avaliação deve citar os IDs do catálogo de evidências fornecido.
Evite linguagem interna, acadêmica ou excessivamente técnica.
Não use expressões como "Sugestão ao profissional", "considerar a combinação", "pode indicar", "fatores clínicos associados" ou "acompanhamento longitudinal".
Prefira frases curtas, humanas e compreensíveis para o cliente.
Todo ponto positivo, atenção ou orientação deve citar source_chunk_ids recuperados.
Responda exclusivamente no JSON Schema solicitado.
PROMPT;
    }

    private function openAiResponseSchema(): array
    {
        $pointSchema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['text', 'source_chunk_ids'],
            'properties' => [
                'text' => ['type' => 'string'],
                'source_chunk_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'status',
                'summary',
                'positive_points',
                'attention_points',
                'general_guidance',
                'professional_observation',
                'prohibited_content_detected',
            ],
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['generated', 'insufficient_evidence']],
                'summary' => ['type' => 'string'],
                'positive_points' => ['type' => 'array', 'items' => $pointSchema],
                'attention_points' => ['type' => 'array', 'items' => $pointSchema],
                'general_guidance' => ['type' => 'array', 'items' => $pointSchema],
                'professional_observation' => ['type' => ['string', 'null']],
                'prohibited_content_detected' => ['type' => 'boolean'],
            ],
        ];
    }

    private function extractOpenAiText(array $response): string
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

    private function sourcesForPrompt(Collection $chunks): array
    {
        return $chunks
            ->map(fn (BioimpedanceKnowledgeChunk $chunk) => [
                'source_chunk_id' => $chunk->chunk_key,
                'documento' => $chunk->document?->title,
                'secao' => $chunk->section,
                'pagina' => $chunk->page,
                'conteudo' => $chunk->content,
            ])
            ->values()
            ->all();
    }

    private function blockedResponse(string $summary): array
    {
        return [
            'status' => 'blocked',
            'summary' => $summary,
            'positive_points' => [],
            'attention_points' => [],
            'general_guidance' => [],
            'professional_observation' => null,
            'prohibited_content_detected' => false,
            'provider' => 'openai',
            'fallback_reason' => null,
        ];
    }

    private function noticeText(array $response): string
    {
        $provider = $response['provider'] ?? 'local_reference_engine';
        $base = $provider === 'openai'
            ? 'Sugestão gerada pela OpenAI e validada pelo backend para revisão do profissional.'
            : 'Sugestão local fundamentada em referências parametrizadas e validada para revisão do profissional.';

        if (filled($response['fallback_reason'] ?? null)) {
            $base .= ' Fallback local utilizado: '.$response['fallback_reason'];
        }

        return $base.' As classificações oficiais não foram alteradas.';
    }

    private function generateStructuredResponse(BioimpedanceAssessment $assessment, array $context, Collection $chunks): array
    {
        $requiredChunks = collect(self::METRIC_CHUNKS)
            ->filter(fn (string $chunkKey, string $metric) => filled($context['metrics'][$metric]['classification'] ?? null))
            ->values()
            ->push('vita-scan-measurement-protocol')
            ->push('vita-scan-assistant-safety-policy');

        if ($requiredChunks->diff($chunks->pluck('chunk_key'))->isNotEmpty()) {
            return [
                'status' => 'insufficient_evidence',
                'summary' => 'A base aprovada não contém todas as fontes necessárias para gerar uma observação segura.',
                'positive_points' => [],
                'attention_points' => [],
                'general_guidance' => [],
                'professional_observation' => null,
                'prohibited_content_detected' => false,
            ];
        }

        $positivePoints = $this->pointsByTone($context, ['good'], $chunks);
        $attentionPoints = $this->pointsByTone($context, ['warning', 'danger', 'attention'], $chunks);
        $variation = $this->variationText($assessment, $context['previous_assessment'] ?? null);
        $warnings = $context['warnings'] ?? [];
        $profileWarning = collect(data_get($context, 'history_comparison.warnings', []))->first();
        $segmentPoints = $this->narrative->segmentPoints($context);
        $reviewIds = collect($context['findings'] ?? [])->where('priority', 'review')->pluck('id')->all();
        $segmentAttention = array_values(array_filter($segmentPoints, fn ($point) => count(array_intersect($point['source_chunk_ids'], $reviewIds)) > 0));
        $attentionPoints = [...$attentionPoints, ...$segmentAttention];
        $observationParts = [
            $this->narrative->composition($context),
            collect($segmentPoints)->pluck('text')->implode(' '),
            data_get($context, 'measurement_profile.athlete_mode') === true
                ? 'O perfil Atleta está registrado nesta medição. Confirme sua adequação com o profissional, pois esse perfil influencia as estimativas de composição corporal.' : null,
            $segmentPoints ? 'Percentuais segmentares são relativos às referências do equipamento, não percentuais de gordura local. Essas estimativas não comprovam força, função ou diagnóstico.' : null,
            $variation,
            $profileWarning ? 'A comparação com a avaliação anterior exige cautela: '.$profileWarning : null,
            count($warnings) ? 'Há alerta de consistência registrado: '.$warnings[0] : null,
            ! data_get($context, 'history_comparison.has_previous')
                ? 'Esta avaliação é uma linha de base: medições futuras em condições semelhantes permitirão acompanhar tendências, com revisão profissional.'
                : 'Confira com o profissional as condições de cada medição antes de atribuir significado às diferenças.',
        ];
        $observation = collect($observationParts)->filter()->implode(' ');
        if (mb_strlen($observation) > 1900) {
            // Compact groups rather than silently discard regions or the conclusion.
            $observationParts[1] = $this->narrative->compactSegments($context);
            $observation = collect($observationParts)->filter()->implode(' ');
        }

        return [
            'status' => 'generated',
            'summary' => 'Sugestão gerada a partir dos resultados oficiais da avaliação e de trechos aprovados da base de conhecimento.',
            'positive_points' => $positivePoints,
            'attention_points' => $attentionPoints,
            'general_guidance' => [[
                'text' => 'Realizar avaliações em condições semelhantes para favorecer comparação entre medições.',
                'source_chunk_ids' => ['vita-scan-measurement-protocol'],
            ]],
            'professional_observation' => $observation,
            'prohibited_content_detected' => false,
        ];
    }

    private function validateResponse(array $response, BioimpedanceAssessment $assessment, Collection $chunks, array $context): array
    {
        $errors = [];
        $allowedChunkKeys = [
            ...$chunks->pluck('chunk_key')->all(),
            ...($context['evidence_ids'] ?? []),
        ];
        $text = $this->responseTextForValidation($response);

        foreach ([
            'diagnóstico de', 'diagnostico de', 'diagnosticado com', 'diagnosticada com',
            'confirma o diagnóstico', 'confirma o diagnostico', 'indica diagnóstico', 'indica diagnostico',
            'prescrevo', 'prescrição de', 'prescricao de', 'tome ', 'dose de', 'mg por',
            'medicamento indicado', 'medicamento recomendado', 'suplemento obrigatório', 'siga a dieta',
            'perda muscular confirmada', 'apresenta perda muscular', 'assimetria clínica identificada',
            'apresenta assimetria clínica', 'comprova função muscular preservada', 'confirma musculatura preservada',
            'diagnóstico pelo whr', 'diagnóstico com base no whr', 'sarcopenia confirmada', 'apresenta sarcopenia',
            'sua idade biológica real é', 'idade biológica real confirmada', 'peso ideal prescrito pelo aplicativo',
            'seu peso ideal é', 'pontuação corporal indica', 'pontuação corporal demonstra',
            'pontuação corporal confirma', 'whr indica', 'whr demonstra',
        ] as $forbidden) {
            if ($this->textGuard->contains($text, $forbidden)) {
                $errors[] = 'Conteúdo proibido detectado: '.$forbidden;
            }
        }

        foreach ([
            'sugestão ao profissional',
            'sugestao ao profissional',
            'considerar a combinação',
            'considerar a combinacao',
            'pode indicar',
            'fatores clínicos associados',
            'fatores clinicos associados',
            'acompanhamento longitudinal',
        ] as $internalPhrase) {
            if (str_contains($text, $internalPhrase)) {
                $errors[] = 'Linguagem interna ou técnica demais para o cliente: '.$internalPhrase;
            }
        }

        foreach (['positive_points', 'attention_points', 'general_guidance'] as $section) {
            foreach ($response[$section] ?? [] as $point) {
                foreach ($point['source_chunk_ids'] ?? [] as $chunkKey) {
                    if (! in_array($chunkKey, $allowedChunkKeys, true)) {
                        $errors[] = 'Fonte inexistente ou não recuperada: '.$chunkKey;
                    }
                }
            }
        }

        $indicators = $assessment->analysis['indicators'] ?? [];
        foreach (self::METRIC_CHUNKS as $metric => $chunkKey) {
            if ($metric !== 'bmi' && in_array($assessment->device_model, ['Relaxmedic', 'Bodyscan Pro'], true)) {
                continue;
            }
            $classification = $indicators[$metric]['classification'] ?? null;
            if (! $classification || ($indicators[$metric]['pending'] ?? false)) {
                continue;
            }

            $classificationLower = Str::lower($classification);
            $metricText = match ($metric) {
                'bmi' => 'imc',
                'body_fat' => 'gordura corporal',
                'skeletal_muscle' => 'músculo esquelético',
                'visceral_fat' => 'gordura visceral',
            };

            if (str_contains($text, $metricText) && ! str_contains($text, $classificationLower)) {
                $errors[] = 'Classificação citada sem correspondência oficial para '.$metricText.'.';
            }
        }

        if (($response['status'] ?? null) === 'generated' && blank($response['professional_observation'] ?? null)) {
            $errors[] = 'Resposta gerada sem observação profissional.';
        }
        if (mb_strlen((string) ($response['professional_observation'] ?? '')) > 2000) {
            $errors[] = 'A observação excede o tamanho permitido para revisão.';
        }

        if ($response['prohibited_content_detected'] ?? false) {
            $errors[] = 'O agente sinalizou conteúdo proibido.';
        }

        return array_values(array_unique($errors));
    }

    private function responseTextForValidation(array $response): string
    {
        $userFacingText = collect([
            $response['summary'] ?? null,
            $response['professional_observation'] ?? null,
            ...collect(['positive_points', 'attention_points', 'general_guidance'])
                ->flatMap(fn (string $section) => collect($response[$section] ?? [])
                    ->pluck('text'))
                ->all(),
        ])
            ->filter(fn ($value) => is_string($value) && filled($value))
            ->implode("\n");

        return Str::lower($userFacingText);
    }

    private function retrieveApprovedChunks(array $context): Collection
    {
        $keys = collect(['vita-scan-measurement-protocol', 'vita-scan-assistant-safety-policy']);
        foreach (self::METRIC_CHUNKS as $metric => $chunkKey) {
            if (filled($context['metrics'][$metric]['classification'] ?? null)) {
                $keys->push($chunkKey);
            }
        }

        $tags = collect(['measurement_protocol', 'safety_policy', 'prohibited_content']);
        foreach (array_keys(self::METRIC_CHUNKS) as $metric) {
            if (filled($context['metrics'][$metric]['classification'] ?? null)) {
                $tags->push($metric);
            }
        }

        return $this->publishedSources
            ->matching(
                $tags->unique()->values()->all(),
                $keys->unique()->values()->all(),
                $context['device_model'] ?? null,
            )
            ->sortBy(fn (BioimpedanceKnowledgeChunk $chunk) => ($position = $keys->search($chunk->chunk_key)) === false ? 999 : $position)
            ->values();
    }

    private function structuredContext(BioimpedanceAssessment $assessment, Collection $history): array
    {
        $indicators = $assessment->analysis['indicators'] ?? [];
        $usesManufacturerOnlyReferences = in_array($assessment->device_model, ['Relaxmedic', 'Bodyscan Pro'], true);
        $clinicalContext = $this->assistantContext->for($assessment);
        $historyComparison = $clinicalContext['history_comparison'] ?? [];
        $comparableMetrics = collect($historyComparison['comparable_metrics'] ?? [])->keyBy('field');

        return [
            'assessment_id' => $assessment->id,
            'device_model' => $assessment->device_model,
            'reference_version' => $assessment->reference_version,
            'age_at_assessment' => $assessment->age_at_assessment,
            'biological_sex_at_assessment' => $assessment->biological_sex_at_assessment,
            'metrics' => [
                'weight' => ['value' => (float) $assessment->weight_kg, 'unit' => 'kg'],
                'bmi' => ['value' => (float) $assessment->calculated_bmi, 'unit' => 'kg/m²', 'classification' => $indicators['bmi']['classification'] ?? null, 'tone' => $indicators['bmi']['tone'] ?? null],
                'body_fat' => ['value' => $assessment->body_fat_percentage === null ? null : (float) $assessment->body_fat_percentage, 'unit' => '%', 'classification' => $usesManufacturerOnlyReferences ? null : ($indicators['body_fat']['classification'] ?? null), 'tone' => $usesManufacturerOnlyReferences ? 'pending' : ($indicators['body_fat']['tone'] ?? null)],
                'skeletal_muscle' => ['value' => $assessment->skeletal_muscle_percentage === null ? null : (float) $assessment->skeletal_muscle_percentage, 'unit' => '%', 'classification' => $usesManufacturerOnlyReferences ? null : ($indicators['skeletal_muscle']['classification'] ?? null), 'tone' => $usesManufacturerOnlyReferences ? 'pending' : ($indicators['skeletal_muscle']['tone'] ?? null)],
                'visceral_fat' => ['value' => $assessment->visceral_fat_level === null ? null : (float) $assessment->visceral_fat_level, 'unit' => 'índice do dispositivo', 'classification' => $usesManufacturerOnlyReferences ? null : ($indicators['visceral_fat']['classification'] ?? null), 'tone' => $usesManufacturerOnlyReferences ? 'pending' : ($indicators['visceral_fat']['tone'] ?? null)],
                'resting_metabolism' => ['value' => $assessment->resting_metabolism_kcal, 'unit' => 'kcal/dia'],
                'body_age' => ['value' => $assessment->body_age, 'unit' => 'anos'],
            ],
            'previous_assessment' => ($historyComparison['automatic_interpretation_allowed'] ?? false) ? [
                'weight_kg' => data_get($comparableMetrics->get('weight_kg'), 'previous'),
                'body_fat_percentage' => data_get($comparableMetrics->get('body_fat_percentage'), 'previous'),
                'skeletal_muscle_percentage' => data_get($comparableMetrics->get('skeletal_muscle_percentage'), 'previous'),
                'visceral_fat_level' => data_get($comparableMetrics->get('visceral_fat_level'), 'previous'),
            ] : null,
            'warnings' => $assessment->analysis['warnings'] ?? [],
            'professional_notes' => $assessment->notes,
            ...$clinicalContext,
        ];
    }

    private function pointsByTone(array $context, array $tones, Collection $chunks): array
    {
        $labels = [
            'bmi' => 'IMC',
            'body_fat' => 'Gordura corporal',
            'skeletal_muscle' => 'Músculo esquelético',
            'visceral_fat' => 'Gordura visceral',
        ];

        return collect($labels)
            ->map(function (string $label, string $metric) use ($context, $tones, $chunks) {
                $item = $context['metrics'][$metric] ?? [];
                if (! in_array($item['tone'] ?? null, $tones, true) || blank($item['classification'] ?? null)) {
                    return null;
                }

                $chunkKey = self::METRIC_CHUNKS[$metric];
                if (! $chunks->contains('chunk_key', $chunkKey)) {
                    return null;
                }

                return [
                    'text' => $label.' classificado como '.mb_strtolower($item['classification']).'.',
                    'source_chunk_ids' => [$chunkKey, 'assessment:metric:'.match ($metric) {
                        'bmi' => 'calculated_bmi',
                        'body_fat' => 'body_fat_percentage',
                        'skeletal_muscle' => 'skeletal_muscle_percentage',
                        'visceral_fat' => 'visceral_fat_level',
                    }],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function variationText(BioimpedanceAssessment $assessment, ?array $previous): ?string
    {
        if (! $previous) {
            return null;
        }

        $manufacturerEstimate = in_array($assessment->device_model, ['Relaxmedic', 'Bodyscan Pro'], true);
        $changes = collect([
            $this->changeText('peso', $previous['weight_kg'], $assessment->weight_kg, 'kg', true),
            $this->changeText('gordura corporal', $previous['body_fat_percentage'], $assessment->body_fat_percentage, 'ponto percentual', $manufacturerEstimate ? null : true),
            $this->changeText('músculo esquelético estimado', $previous['skeletal_muscle_percentage'], $assessment->skeletal_muscle_percentage, 'ponto percentual', $manufacturerEstimate ? null : false),
            $this->changeText('gordura visceral', $previous['visceral_fat_level'], $assessment->visceral_fat_level, 'nível', $manufacturerEstimate ? null : true, 0),
        ])->filter();

        return $changes->isEmpty() ? null : 'Em relação à avaliação anterior, houve '.$changes->implode(', ').'.';
    }

    private function changeText(string $label, mixed $previousValue, mixed $currentValue, string $unit, ?bool $lowerIsAttention, int $decimals = 1): ?string
    {
        if ($previousValue === null || $currentValue === null) {
            return null;
        }

        $delta = round((float) $currentValue - (float) $previousValue, $decimals);
        if ($delta == 0.0) {
            return 'manutenção de '.$label;
        }

        $direction = $delta > 0 ? 'aumento' : 'redução';
        $attention = $lowerIsAttention !== null
            && (($lowerIsAttention && $delta > 0) || (! $lowerIsAttention && $delta < 0));

        return $direction.' de '.$this->number(abs($delta), $decimals).' '.$unit.($unit === 'nível' && abs($delta) != 1.0 ? 's' : '').' em '.$label.($attention ? ', ponto de atenção no acompanhamento' : '');
    }

    private function historyBefore(BioimpedanceAssessment $assessment): Collection
    {
        return BioimpedanceAssessment::query()
            ->where('bioimpedance_client_id', $assessment->bioimpedance_client_id)
            ->whereNull('canceled_at')
            ->where('device_model', $assessment->device_model)
            ->where('evaluated_at', '<', $assessment->evaluated_at)
            ->orderBy('evaluated_at')
            ->get();
    }

    private function ensureDefaultKnowledgeBase(?User $approvedBy = null): void
    {
        foreach (self::DEFAULT_DOCUMENTS as $documentData) {
            $chunks = $documentData['chunks'];
            unset($documentData['chunks']);

            $document = BioimpedanceKnowledgeDocument::query()->firstOrCreate(
                ['title' => $documentData['title'], 'version' => $documentData['version']],
                [
                    ...$documentData,
                    'status' => BioimpedanceKnowledgeDocument::STATUS_PUBLISHED,
                    'approved' => true,
                    'approved_by_user_id' => $approvedBy?->id,
                    'approved_at' => now(),
                    'published_by_user_id' => $approvedBy?->id,
                    'published_at' => now(),
                ]
            );

            foreach ($chunks as $chunkData) {
                BioimpedanceKnowledgeChunk::query()->firstOrCreate(
                    ['chunk_key' => $chunkData['chunk_key']],
                    [
                        ...$chunkData,
                        'bioimpedance_knowledge_document_id' => $document->id,
                        'language' => 'pt-BR',
                        'checksum' => hash('sha256', $chunkData['content']),
                    ]
                );
            }
        }
    }

    private function sourcesPayload(Collection $chunks): array
    {
        return $chunks
            ->map(fn (BioimpedanceKnowledgeChunk $chunk) => [
                'id' => $chunk->chunk_key,
                'document' => $chunk->document?->title,
                'document_type' => $chunk->document?->document_type,
                'manufacturer' => $chunk->document?->manufacturer,
                'device_model' => $chunk->document?->device_model,
                'version' => $chunk->document?->version,
                'publication_status' => $chunk->document?->status,
                'published_at' => $chunk->document?->published_at?->toIso8601String(),
                'section' => $chunk->section,
                'page' => $chunk->page,
                'checksum' => $chunk->checksum,
            ])
            ->values()
            ->all();
    }

    private function number(mixed $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}
