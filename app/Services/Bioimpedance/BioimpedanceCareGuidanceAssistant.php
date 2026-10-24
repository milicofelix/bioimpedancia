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
    public const PROMPT_VERSION = 'care-guidance-2.0.0';

    public function __construct(private readonly CareGuidanceSemanticValidator $semanticValidator) {}

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
            'title' => 'Política de segurança das orientações Ricosty',
            'version' => '1.0.0',
            'source_url' => null,
            'chunks' => [
                [
                    'chunk_key' => 'ricosty-care-guidance-safety',
                    'section' => 'Limites clínicos',
                    'tags' => ['safety', 'prohibited_content'],
                    'content' => 'Não diagnosticar doenças, interpretar exames não registrados, prescrever dieta individualizada, exercício, medicamento, vitamina ou suplemento. Não usar apenas IMC ou peso ideal do fabricante para definir meta. Não afirmar preservação muscular a partir de percentual estimado sem avaliação de força e função. Recomendações devem ser gerais, condicionadas à avaliação de profissional habilitado e baseadas somente nos dados disponíveis.',
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

        $generation = $this->generate($context, $chunks);
        $validation = $this->semanticValidator->validate($generation['payload'], $chunks, $context);
        $blockedClaims = $validation['blocked_claims'];

        if ($validation['errors'] && $generation['provider'] === 'openai') {
            $generation = [
                'payload' => $this->localResponse($context),
                'provider' => 'local_reference_engine',
                'attempted_provider' => 'openai',
                'fallback_used' => true,
                'fallback_reason' => 'A resposta externa foi bloqueada pela validação semântica.',
            ];
            $validation = $this->semanticValidator->validate($generation['payload'], $chunks, $context);
        }

        $validationErrors = $validation['errors'];
        $blockedClaims = [...$blockedClaims, ...$validation['blocked_claims']];
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
            'section' => $chunk->section,
            'url' => $chunk->document?->source_url,
            'claim_ids' => collect($validation['accepted_claims'])
                ->filter(fn (array $claim) => in_array($chunk->chunk_key, $claim['source_ids'], true))
                ->pluck('id')->values()->all(),
        ])->values()->all();

        return [
            'status' => $status,
            'summary' => $status === 'generated' ? 'Orientações gerais geradas para revisão profissional.' : 'Não foi possível gerar orientações seguras.',
            'suggestion' => $output->care_guidance,
            'output_id' => $output->id,
            'validation_errors' => $validationErrors,
            'claims' => $validation['accepted_claims'],
            'blocked_claims' => $blockedClaims,
            'sources' => $sources,
            'provider' => $generation['provider'],
            'attempted_provider' => $generation['attempted_provider'],
            'fallback_used' => $generation['fallback_used'],
            'fallback_reason' => $generation['fallback_reason'],
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
                ->acceptJson()->asJson()->timeout((int) config('services.openai.timeout', 45))
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                    'model' => config('services.openai.model', 'gpt-5.1'),
                    'input' => [
                        ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $this->systemPrompt()]]],
                        ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => json_encode([
                            'contexto' => $context,
                            'fontes' => $chunks->map(fn ($chunk) => ['id' => $chunk->chunk_key, 'conteudo' => $chunk->content])->values()->all(),
                            'fontes_internas_disponiveis' => collect([
                                'assessment:current',
                                ! empty($context['history_changes']) ? 'assessment:history' : null,
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
Você é o agente de orientações educativas da Ricosty. Escreva em português do Brasil para o cliente, usando apenas o contexto, a observação aprovada, o histórico e as fontes fornecidas. Relacione os hábitos somente aos pontos de atenção e tendências realmente presentes, sem inventar doença, exame, colesterol, deficiência vitamínica ou contraindicação. Não diagnostique nem prescreva dieta, exercício, medicamento, vitamina ou suplemento. Não defina objetivo somente por IMC ou peso ideal do fabricante e não afirme musculatura preservada a partir de percentual estimado. Para pessoas idosas, priorize preservação de força, função, equilíbrio e autonomia, com avaliação profissional. Ofereça opções gerais, realistas e graduais, sempre condicionando atividade física à capacidade e eventual liberação profissional. Não prometa resultados. Divida a resposta em afirmações independentes. Cada afirmação deve informar categoria, texto, nível de segurança, revisão profissional obrigatória e todas as fontes que a sustentam. Use os IDs das fontes fornecidas e, para dados internos, somente assessment:current, assessment:history ou observation:approved quando disponíveis. Não esconda incertezas elevando o nível de segurança. Responda apenas no schema solicitado.
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
                        'required' => ['id', 'category', 'text', 'confidence', 'requires_professional_review', 'source_ids'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'category' => ['type' => 'string', 'enum' => CareGuidanceSemanticValidator::CATEGORIES],
                            'text' => ['type' => 'string'],
                            'confidence' => ['type' => 'string', 'enum' => CareGuidanceSemanticValidator::CONFIDENCE_LEVELS],
                            'requires_professional_review' => ['type' => 'boolean'],
                            'source_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function localResponse(array $context): array
    {
        $claims = [];
        $attention = collect($context['attention_points'])->implode(', ');
        $claims[] = $this->claim('assessment-context', 'assessment_context', $attention
            ? 'Os pontos que merecem atenção nesta avaliação são: '.$attention.'. Eles devem ser interpretados em conjunto com o histórico e a avaliação profissional.'
            : 'Esta avaliação isolada não justifica uma conduta individualizada; os resultados devem ser interpretados pelo profissional junto ao histórico.', 'moderate', ['assessment:current', 'observation:approved']);
        if (! empty($context['history_changes'])) {
            $claims[] = $this->claim('assessment-history', 'assessment_context', 'Em relação à avaliação anterior, foi observada '.collect($context['history_changes'])->implode(', ').'. A tendência depende também das condições de cada medição.', 'moderate', ['assessment:history']);
        }
        if (($context['age_at_assessment'] ?? 0) >= 60) {
            $claims[] = $this->claim('older-adult-function', 'movement_education', 'Nesta faixa etária, preservar força, equilíbrio, mobilidade e autonomia merece atenção; a estimativa muscular da balança, isoladamente, não comprova função preservada.', 'high', ['assessment:current', 'who-older-adult-functional-activity', 'brazil-older-adult-functionality']);
        }
        $claims[] = $this->claim('nutrition-general', 'nutrition_education', 'Priorize refeições variadas baseadas em alimentos in natura ou minimamente processados, incluindo frutas, legumes, verduras e feijões, e reduza a frequência de ultraprocessados e bebidas açucaradas.', 'high', ['brazil-food-guide-natural-foods', 'brazil-food-guide-ultraprocessed']);
        $claims[] = $this->claim('movement-general', 'movement_education', 'Se estiver apto, aumente o movimento gradualmente. Caminhada, bicicleta, dança e fortalecimento são opções que devem ser adaptadas à capacidade individual e à orientação profissional.', 'high', ['who-adult-physical-activity', 'ricosty-care-guidance-safety']);
        $claims[] = $this->claim('professional-follow-up', 'professional_follow_up', 'Defina metas pequenas com o profissional e procure avaliação individualizada antes de usar vitaminas, suplementos ou iniciar atividades incompatíveis com suas condições e limitações.', 'high', ['ricosty-care-guidance-safety']);

        return ['claims' => $claims];
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
        $indicators = $assessment->analysis['indicators'] ?? [];
        $labels = ['bmi' => 'IMC', 'body_fat' => 'gordura corporal', 'skeletal_muscle' => 'músculo esquelético', 'visceral_fat' => 'gordura visceral'];
        $attention = collect($labels)->map(function ($label, $key) use ($indicators) {
            $indicator = $indicators[$key] ?? [];
            return in_array($indicator['tone'] ?? null, ['warning', 'danger', 'attention'], true) && filled($indicator['classification'] ?? null)
                ? $label.' '.mb_strtolower($indicator['classification']) : null;
        })->filter()->values()->all();
        if ($assessment->device_model === 'Relaxmedic') {
            $attention = [];
        }
        $previous = $assessment->client->assessments()
            ->where('id', '!=', $assessment->id)
            ->whereNull('canceled_at')
            ->where('evaluated_at', '<', $assessment->evaluated_at)
            ->latest('evaluated_at')
            ->first();
        $historyChanges = $previous ? collect([
            $this->changeText('peso', $previous->weight_kg, $assessment->weight_kg, 'kg'),
            $this->changeText('gordura corporal', $previous->body_fat_percentage, $assessment->body_fat_percentage, 'ponto percentual'),
            $this->changeText('massa magra', $previous->lean_body_mass_kg, $assessment->lean_body_mass_kg, 'kg'),
            $this->changeText('músculo esquelético estimado', $previous->skeletal_muscle_percentage, $assessment->skeletal_muscle_percentage, 'ponto percentual'),
        ])->filter()->values()->all() : [];

        return [
            'assessment_id' => $assessment->id,
            'assessment_updated_at' => $assessment->updated_at?->toIso8601String(),
            'age_at_assessment' => $assessment->age_at_assessment,
            'professional_observation' => $assessment->notes,
            'attention_points' => $attention,
            'device_model' => $assessment->device_model,
            'history_changes' => $historyChanges,
            'available_data_notice' => 'A avaliação contém composição corporal; não contém exames laboratoriais, diagnósticos, medicações, alergias ou deficiências vitamínicas.',
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
