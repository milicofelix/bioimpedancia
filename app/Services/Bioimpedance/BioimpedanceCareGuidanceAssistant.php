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

class BioimpedanceCareGuidanceAssistant
{
    public const PROMPT_VERSION = 'care-guidance-2.5.0';

    public function __construct(
        private readonly CareGuidanceResponseReview $responseReview,
        private readonly KnowledgeSourceSnapshot $sourceSnapshot,
        private readonly PublishedKnowledgeSourceRepository $publishedSources,
        private readonly BioimpedanceAssistantContext $assistantContext,
        private readonly ApprovedObservationContext $approvedObservation,
        private readonly ContextualCareEducation $careEducation,
        private readonly AssessmentFindingPrioritizer $findingPrioritizer,
        private readonly AssessmentNarrative $narrative,
    ) {}

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
                [
                    'chunk_key' => 'who-older-adult-functional-activity',
                    'section' => 'Atividade física para pessoas idosas',
                    'tags' => ['physical_activity', 'older_adult', 'function'],
                    'content' => 'Para pessoas com 65 anos ou mais, a OMS recomenda atividade multicomponente que enfatize equilíbrio funcional e fortalecimento. Pessoas inativas devem começar com pequenas quantidades e progredir conforme capacidades, condições e orientação profissional.',
                ],
            ],
        ],
        [
            'title' => 'Saúde da Pessoa Idosa — Ministério da Saúde',
            'version' => 'consulta-2026',
            'source_url' => 'https://www.gov.br/saude/pt-br/assuntos/saude-de-a-a-z/s/saude-da-pessoa-idosa',
            'chunks' => [[
                'chunk_key' => 'brazil-older-adult-functionality',
                'section' => 'Avaliação multidimensional e funcionalidade',
                'tags' => ['older_adult', 'function', 'safety'],
                'content' => 'O cuidado da pessoa idosa deve considerar funcionalidade, contexto de vida e suporte social. Força muscular, equilíbrio e mobilidade não devem ser inferidos somente por peso, IMC ou estimativas de composição corporal.',
            ]],
        ],
        [
            'title' => 'Política de segurança das orientações Vita Scan',
            'version' => '1.0.0',
            'source_url' => null,
            'chunks' => [
                [
                    'chunk_key' => 'vita-scan-care-guidance-safety',
                    'section' => 'Limites clínicos',
                    'tags' => ['safety', 'prohibited_content'],
                    'content' => 'Não diagnosticar doenças, interpretar exames não registrados, prescrever dieta individualizada, exercício, medicamento, vitamina ou suplemento. Não usar apenas IMC ou peso ideal do fabricante para definir meta. Não afirmar preservação muscular a partir de percentual estimado sem avaliação de força e função. Recomendações devem ser gerais, condicionadas à avaliação de profissional habilitado e baseadas somente nos dados disponíveis.',
                ],
            ],
        ],
    ];

    public function suggest(BioimpedanceAssessment $assessment, User $requestedBy): array
    {
        $assessment->loadMissing(['client', 'segmentalMeasurements', 'extendedMetrics', 'impedanceReadings']);
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
                'source_snapshot' => $this->sourceSnapshot->fromChunk($chunk),
            ]);
        }

        $generation = $this->generate($context, $chunks);
        $review = $this->responseReview->review($generation, $chunks, $context, fn () => $this->localResponse($context));
        $generation = $review['generation'];
        $validation = $review['validation'];
        $externalValidationErrors = $review['external_errors'];
        $externalPayload = $review['external_payload'];
        $validationErrors = $validation['errors'];
        $blockedClaims = $review['blocked_claims'];
        $status = $validationErrors ? 'blocked' : 'generated';
        $request->update([
            'status' => $status,
            'validation_errors' => $validationErrors,
            'provider' => $generation['provider'],
            'attempted_provider' => $generation['attempted_provider'],
            'fallback_used' => $generation['fallback_used'],
            'fallback_reason' => $generation['fallback_reason'],
            'model_name' => $generation['provider'] === 'openai' ? $this->modelName() : 'local-care-guidance-agent',
        ]);
        $guidance = $status === 'generated' ? $this->renderGuidance($validation['accepted_claims']) : null;
        $output = BioimpedanceAiAnalysisOutput::query()->create([
            'bioimpedance_ai_analysis_request_id' => $request->id,
            'raw_response' => [
                'payload' => $generation['payload'],
                'provider' => $generation['provider'],
                'attempted_provider' => $generation['attempted_provider'],
                'fallback_used' => $generation['fallback_used'],
                'fallback_reason' => $generation['fallback_reason'],
                'blocked_provider_errors' => $externalValidationErrors,
                'validation_errors' => $validationErrors,
                'generation_audit' => [
                    'external_validation_errors' => $externalValidationErrors,
                    'final_validation_errors' => $validationErrors,
                    'external_response' => $externalPayload,
                    'attempted_model' => $generation['attempted_provider'] ? (string) config('services.openai.model', 'gpt-5.1') : null,
                ],
            ],
            'structured_claims' => $validation['accepted_claims'],
            'blocked_claims' => $blockedClaims,
            'care_guidance' => $guidance,
            'validation_status' => $validationErrors ? 'failed' : 'passed',
        ]);

        $usedSourceIds = collect($validation['accepted_claims'])->pluck('source_ids')->flatten()->unique();
        $sources = $chunks->whereIn('chunk_key', $usedSourceIds)->map(fn ($chunk) => [
            'id' => $chunk->chunk_key,
            'document' => $chunk->document?->title,
            'version' => $chunk->document?->version,
            'publication_status' => $chunk->document?->status,
            'published_at' => $chunk->document?->published_at?->toIso8601String(),
            'section' => $chunk->section,
            'url' => $chunk->document?->source_url,
            'checksum' => $chunk->checksum,
            'claim_ids' => collect($validation['accepted_claims'])
                ->filter(fn (array $claim) => in_array($chunk->chunk_key, $claim['source_ids'], true))
                ->pluck('id')->values()->all(),
        ])->values()->all();
        $internalSources = collect($context['evidence_catalog'] ?? [])
            ->filter(fn (array $evidence) => $usedSourceIds->contains($evidence['id'] ?? null))
            ->map(fn (array $evidence) => [
                ...$evidence,
                'published_at' => null,
                'claim_ids' => collect($validation['accepted_claims'])
                    ->filter(fn (array $claim) => in_array($evidence['id'], $claim['source_ids'] ?? [], true))
                    ->pluck('id')->values()->all(),
            ])->values()->all();
        $sources = [...$sources, ...$internalSources];

        return [
            'status' => $status,
            'summary' => $status === 'generated' ? 'Orientações gerais geradas para revisão profissional.' : 'Não foi possível gerar orientações seguras.',
            'suggestion' => $output->care_guidance,
            'output_id' => $output->id,
            'request_id' => $request->id,
            'validation_errors' => $validationErrors,
            'claims' => $validation['accepted_claims'],
            'blocked_claims' => $blockedClaims,
            'sources' => $sources,
            'provider' => $generation['provider'],
            'attempted_provider' => $generation['attempted_provider'],
            'fallback_used' => $generation['fallback_used'],
            'fallback_reason' => $generation['fallback_reason'],
            'blocked_provider_errors' => $externalValidationErrors,
            'notice' => 'Orientação educativa, sem diagnóstico ou prescrição. Exige aprovação do profissional.',
        ];
    }

    private function generate(array $context, Collection $chunks): array
    {
        if (config('bioimpedance.assistant.provider') === 'local_reference_engine') {
            return $this->localGeneration($context);
        }
        if (! filled(config('services.openai.api_key'))) {
            return $this->localGeneration($context, 'Provedor externo indisponível na configuração atual.', 'openai');
        }

        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->acceptJson()->asJson()->connectTimeout(10)->timeout((int) config('services.openai.timeout', 45))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                    'model' => config('services.openai.model', 'gpt-5.1'),
                    'store' => false,
                    'input' => [
                        ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $this->systemPrompt()]]],
                        ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => json_encode([
                            'contexto' => $context,
                            'fontes' => $chunks->map(fn ($chunk) => ['id' => $chunk->chunk_key, 'conteudo' => $chunk->content])->values()->all(),
                            'fontes_internas_disponiveis' => collect([
                                ...($context['evidence_ids'] ?? []),
                                filled($context['professional_observation'] ?? null) ? 'observation:approved' : null,
                            ])->filter()->values()->all(),
                        ], JSON_UNESCAPED_UNICODE)]]],
                    ],
                    'text' => ['format' => ['type' => 'json_schema', 'name' => 'care_guidance', 'strict' => true, 'schema' => $this->schema()]],
                ])->throw()->json();

            $payload = json_decode($this->outputText($response), true);
            if (! is_array($payload)) {
                return $this->localGeneration($context, 'A OpenAI retornou uma resposta fora do JSON esperado.', 'openai');
            }

            return [
                'payload' => $payload,
                'provider' => 'openai',
                'attempted_provider' => 'openai',
                'fallback_used' => false,
                'fallback_reason' => null,
            ];
        } catch (ConnectionException|RequestException) {
            return $this->localGeneration($context, 'Falha de comunicação com o provedor externo.', 'openai');
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Use approved_observation como a versão aprovada do primeiro agente, inclusive quando o profissional editou o texto. Os findings do contexto são fatos da avaliação, não conclusões clínicas aprovadas automaticamente. Relacione cada orientação aos achados pertinentes ou à observação aprovada, além da referência educativa. Cite os achados em finding_ids e inclua seus IDs também em source_ids. Não use somente assessment:current para justificar personalização. Informe comparison_scope: none, profile_only ou numeric_change. numeric_change só é permitido quando history_comparison.automatic_interpretation_allowed for verdadeiro. Não invente alimentação, atividade, sintomas ou contraindicações que não foram registrados. Na ausência dessas informações, explique o que precisa ser avaliado antes de individualizar cuidados. Limite-se a dez afirmações e 3500 caracteres somados.
Priorize segundo finding_selection_policy e prioritized_findings, sem escolher apenas os primeiros três indicadores. Considere todas as regiões, inclusive o tronco, resumindo grupos equivalentes. Percentual relativo à referência segmentar não é percentual de gordura corporal regional. Use missing_personal_context para explicitar lacunas, não para presumir respostas. Inclua possibilidades práticas e não prescritivas de alimentos e atividades, relacionando a justificativa aos achados e à síntese aprovada. Não repita a síntese inteira ou as ressalvas em cada parágrafo. Cada texto deve ter no máximo 850 caracteres. Orientações sobre consulta/revisão devem citar fonte safety; alimentação, food; movimento, physical_activity, além das evidências internas. Sempre requires_professional_review: true; finding_ids só contém IDs existentes em findings e também em source_ids; observation:approved é fonte, não finding_id. Não inclua dose, carga, meta de peso, calorias, quantidade ou frequência individualizadas.
Você é o agente de orientações educativas da Vita Scan. Escreva em português do Brasil para o cliente, usando apenas o contexto, a observação aprovada, o histórico e as fontes fornecidas. Relacione os hábitos somente aos pontos de atenção e tendências realmente presentes, sem inventar doença, exame, colesterol, deficiência vitamínica ou contraindicação. Não diagnostique nem prescreva dieta, exercício, medicamento, vitamina ou suplemento. Não defina objetivo por IMC, peso-alvo ou peso ideal do fabricante e não afirme perda muscular, sarcopenia, assimetria clínica ou musculatura e função preservadas a partir das estimativas da balança. Não trate idade corporal como idade biológica real, WHR como diagnóstico ou pontuação corporal como medida clínica. WHR, ASMI, segmentos e impedâncias podem sustentar somente descrições cautelosas dos valores, sempre citando seus IDs de evidência. Considere aplicativo, layout e modo atleta. Se os perfis do histórico forem incompatíveis, informe isso e não classifique a diferença como melhora ou piora. Para pessoas idosas, priorize preservação de força, função, equilíbrio e autonomia, com avaliação profissional. Considere que hidratação, alimentação, horário e atividade recente podem alterar a medição. Ofereça opções gerais, realistas e graduais, sempre condicionando atividade física à capacidade e eventual liberação profissional. Não prometa resultados. Divida a resposta em afirmações independentes. Cada afirmação deve informar categoria, texto, nível de segurança, revisão profissional obrigatória e todas as fontes que a sustentam. Use os IDs das fontes fornecidas e, para dados internos, somente os IDs explicitamente disponíveis no contexto. Não esconda incertezas elevando o nível de segurança. Responda apenas no schema solicitado.
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['claims'],
            'properties' => [
                'claims' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'category', 'text', 'confidence', 'requires_professional_review', 'source_ids', 'finding_ids', 'comparison_scope'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'category' => ['type' => 'string', 'enum' => CareGuidanceSemanticValidator::CATEGORIES],
                            'text' => ['type' => 'string'],
                            'confidence' => ['type' => 'string', 'enum' => CareGuidanceSemanticValidator::CONFIDENCE_LEVELS],
                            'requires_professional_review' => ['type' => 'boolean'],
                            'source_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'finding_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'comparison_scope' => ['type' => 'string', 'enum' => ['none', 'profile_only', 'numeric_change']],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function localResponse(array $context): array
    {
        $claims = [];
        $assessmentSources = ['assessment:current'];
        if (filled($context['professional_observation'] ?? null)) {
            $assessmentSources[] = 'observation:approved';
        }
        $assessmentSources = [...$assessmentSources, ...collect($context['prioritized_findings'] ?? [])->pluck('id')->all()];
        $paragraphs = [''];
        foreach ($context['attention_points'] as $point) {
            $index = count($paragraphs) - 1;
            if (mb_strlen($paragraphs[$index].' '.$point) > 650) {
                $paragraphs[] = $point;
            } else {
                $paragraphs[$index] = trim($paragraphs[$index].' '.$point);
            }
        }
        foreach ($paragraphs as $index => $paragraph) {
            $claims[] = $this->claim('assessment-context-'.($index + 1), 'assessment_context', $paragraph
                ? 'Registros para contextualizar com o profissional: '.$paragraph.' Não definem, isoladamente, diagnóstico ou conduta.'
                : 'Esta avaliação isolada não justifica uma conduta individualizada; os resultados devem ser interpretados pelo profissional junto ao histórico.', 'moderate', $assessmentSources);
        }
        if (! empty($context['history_changes'])) {
            $claims[] = $this->claim('assessment-history', 'assessment_context', 'Em relação à avaliação anterior comparável, foi observada '.collect($context['history_changes'])->implode(', ').'. A tendência depende também das condições de cada medição.', 'moderate', ['assessment:history-profile']);
        }
        $profileWarnings = collect(data_get($context, 'history_comparison.warnings', []))->implode(' ');
        if (data_get($context, 'history_comparison.has_previous') && ($profileWarnings || ! data_get($context, 'history_comparison.automatic_interpretation_allowed'))) {
            $claims[] = $this->claim('assessment-profile-limit', 'assessment_context', 'A comparação exige cautela porque o perfil de medição possui diferenças. '.($profileWarnings ?: 'Os valores podem ser exibidos, mas não devem ser classificados automaticamente como melhora ou piora.'), 'high', ['assessment:history-profile']);
        }
        $claims = [...$claims, ...$this->careEducation->claims($context)];

        $findingIds = collect($context['findings'] ?? [])->pluck('id')->all();

        return ['claims' => array_map(fn (array $claim) => [
            ...$claim,
            'finding_ids' => array_values(array_intersect($claim['source_ids'], $findingIds)),
            'comparison_scope' => match ($claim['id']) {
                'assessment-history' => 'numeric_change',
                'assessment-profile-limit' => 'profile_only',
                default => 'none',
            },
        ], $claims)];
    }

    private function localGeneration(array $context, ?string $reason = null, ?string $attemptedProvider = null): array
    {
        return [
            'payload' => $this->localResponse($context),
            'provider' => 'local_reference_engine',
            'attempted_provider' => $attemptedProvider,
            'fallback_used' => $attemptedProvider !== null,
            'fallback_reason' => $reason,
        ];
    }

    private function claim(string $id, string $category, string $text, string $confidence, array $sourceIds): array
    {
        return [
            'id' => $id,
            'category' => $category,
            'text' => $text,
            'confidence' => $confidence,
            'requires_professional_review' => true,
            'source_ids' => $sourceIds,
        ];
    }

    private function renderGuidance(array $claims): string
    {
        $labels = [
            'assessment_context' => 'Contexto da avaliação',
            'nutrition_education' => 'Alimentação',
            'movement_education' => 'Movimento e função',
            'professional_follow_up' => 'Acompanhamento',
            'safety_limit' => 'Cuidados importantes',
        ];

        return collect($claims)
            ->groupBy('category')
            ->map(fn (Collection $items, string $category) => ($labels[$category] ?? 'Orientação').': '.$items->pluck('text')->implode(' '))
            ->implode("\n\n");
    }

    private function context(BioimpedanceAssessment $assessment): array
    {
        $clinicalContext = $this->assistantContext->for($assessment);
        $approvedObservation = $this->approvedObservation->for($assessment, $clinicalContext);
        $segmentalAnalysis = $clinicalContext['segmental_analysis'] ?? null;
        $prioritized = $this->findingPrioritizer->attention($clinicalContext['findings'] ?? []);
        $attention = collect($prioritized)->where('kind', '!=', 'segmental')->pluck('text')->all();
        foreach (['fat', 'muscle'] as $type) {
            $overview = $this->narrative->classificationOverview($prioritized, $type);
            if ($overview !== '') {
                $attention[] = $overview;
            }
        }
        $historyChanges = collect(data_get($clinicalContext, 'history_comparison.comparable_metrics', []))
            ->whereIn('field', [
                'weight_kg',
                'body_fat_percentage',
                'skeletal_muscle_percentage',
                'visceral_fat_level',
                'lean_body_mass_kg',
                'body_water_percentage',
            ])
            ->map(fn (array $metric) => $this->changeText(
                mb_strtolower($metric['label']),
                $metric['previous'],
                $metric['current'],
                $metric['unit'],
            ))->filter()->values()->all();

        return [
            'assessment_id' => $assessment->id,
            'assessment_updated_at' => $assessment->updated_at?->toIso8601String(),
            'age_at_assessment' => $assessment->age_at_assessment,
            'professional_observation' => $approvedObservation['text'],
            'approved_observation' => $approvedObservation,
            'attention_points' => $attention,
            'prioritized_findings' => $prioritized,
            'device_model' => $assessment->device_model,
            'history_changes' => $historyChanges,
            'segmental_analysis' => $segmentalAnalysis,
            'available_data_notice' => 'A avaliação contém composição corporal; não contém exames laboratoriais, diagnósticos, medicações, alergias ou deficiências vitamínicas.',
            ...$clinicalContext,
        ];
    }

    private function changeText(string $label, mixed $previous, mixed $current, string $unit): ?string
    {
        if ($previous === null || $current === null) {
            return null;
        }

        $difference = round((float) $current - (float) $previous, 1);
        if ($difference == 0.0) {
            return 'estabilidade de '.$label;
        }

        return ($difference > 0 ? 'aumento' : 'redução').' de '.number_format(abs($difference), 1, ',', '.').' '.$unit.' em '.$label;
    }

    private function approvedChunks(User $user): Collection
    {
        foreach (self::SOURCE_DEFINITIONS as $definition) {
            $document = BioimpedanceKnowledgeDocument::query()->firstOrCreate(
                ['title' => $definition['title'], 'version' => $definition['version']],
                [
                    'document_type' => 'clinical_reference',
                    'source_url' => $definition['source_url'],
                    'status' => BioimpedanceKnowledgeDocument::STATUS_PUBLISHED,
                    'approved' => true,
                    'approved_by_user_id' => $user->id,
                    'approved_at' => now(),
                    'published_by_user_id' => $user->id,
                    'published_at' => now(),
                ]
            );
            foreach ($definition['chunks'] as $chunk) {
                BioimpedanceKnowledgeChunk::query()->firstOrCreate(['chunk_key' => $chunk['chunk_key']], [
                    'bioimpedance_knowledge_document_id' => $document->id,
                    'section' => $chunk['section'], 'language' => 'pt-BR', 'tags' => $chunk['tags'],
                    'content' => $chunk['content'], 'checksum' => hash('sha256', $chunk['content']),
                ]);
            }
        }

        $keys = collect(self::SOURCE_DEFINITIONS)->pluck('chunks')->flatten(1)->pluck('chunk_key')->all();

        return $this->publishedSources->matching([
            'food',
            'physical_activity',
            'older_adult',
            'function',
            'safety',
            'general_guidance',
            'prohibited_content',
        ], $keys);
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
