<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAiAnalysisOutput;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisRequest;
use App\Models\Bioimpedance\BioimpedanceAiAnalysisSource;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceKnowledgeChunk;
use App\Models\Bioimpedance\BioimpedanceKnowledgeDocument;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BioimpedanceObservationAssistant
{
    public const PROMPT_VERSION = 'rag-guardrails-1.0.0';

    public const MODEL_NAME = 'local-validated-rag-agent';

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
            'title' => 'Protocolo de medição Ricosty',
            'document_type' => 'clinic_protocol',
            'manufacturer' => null,
            'device_model' => null,
            'version' => '1.0.0',
            'source_url' => null,
            'chunks' => [
                [
                    'chunk_key' => 'ricosty-measurement-protocol',
                    'section' => 'Condições de medição',
                    'page' => null,
                    'tags' => ['measurement_protocol', 'guidance'],
                    'content' => 'As avaliações devem ser comparadas preferencialmente em condições semelhantes de hidratação, alimentação, horário e rotina prévia, pois esses fatores podem influenciar estimativas de bioimpedância.',
                ],
                [
                    'chunk_key' => 'ricosty-assistant-safety-policy',
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
        $assessment->loadMissing(['client', 'professional']);
        $this->ensureDefaultKnowledgeBase($requestedBy);

        $history = $this->historyBefore($assessment);
        $context = $this->structuredContext($assessment, $history);
        $chunks = $this->retrieveApprovedChunks($context);
        $request = BioimpedanceAiAnalysisRequest::query()->create([
            'bioimpedance_assessment_id' => $assessment->id,
            'requested_by_user_id' => $requestedBy?->id,
            'prompt_version' => self::PROMPT_VERSION,
            'reference_version' => $assessment->reference_version,
            'model_name' => self::MODEL_NAME,
            'status' => 'processing',
            'structured_context' => $context,
        ]);

        foreach ($chunks as $index => $chunk) {
            BioimpedanceAiAnalysisSource::query()->create([
                'bioimpedance_ai_analysis_request_id' => $request->id,
                'bioimpedance_knowledge_chunk_id' => $chunk->id,
                'relevance_score' => 100 - ($index * 5),
            ]);
        }

        $response = $this->generateStructuredResponse($assessment, $context, $chunks);
        $validationErrors = $this->validateResponse($response, $assessment, $chunks);
        $status = count($validationErrors) ? 'blocked' : $response['status'];

        $request->update([
            'status' => $status,
            'validation_errors' => $validationErrors,
        ]);

        $output = BioimpedanceAiAnalysisOutput::query()->create([
            'bioimpedance_ai_analysis_request_id' => $request->id,
            'raw_response' => $response,
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
            'notice' => 'Sugestão RAG validada para revisão do profissional. As classificações oficiais não foram alteradas.',
        ];
    }

    private function generateStructuredResponse(BioimpedanceAssessment $assessment, array $context, Collection $chunks): array
    {
        $requiredChunks = collect(self::METRIC_CHUNKS)
            ->filter(fn (string $chunkKey, string $metric) => filled($context['metrics'][$metric]['classification'] ?? null))
            ->values()
            ->push('ricosty-measurement-protocol')
            ->push('ricosty-assistant-safety-policy');

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
        $bodyAge = $this->bodyAgeText($assessment);
        $warnings = $context['warnings'] ?? [];
        $observationParts = [
            $this->classificationObservation($context),
            $bodyAge,
            $variation,
            count($warnings) ? 'Há alerta de consistência registrado: '.$warnings[0] : null,
            'Recomenda-se acompanhamento periódico da evolução corporal, mantendo condições semelhantes entre as medições e avaliação individualizada com profissional habilitado para definição de condutas.',
        ];
        $observation = collect($observationParts)->filter()->implode(' ');

        return [
            'status' => 'generated',
            'summary' => 'Sugestão gerada a partir dos resultados oficiais da avaliação e de trechos aprovados da base de conhecimento.',
            'positive_points' => $positivePoints,
            'attention_points' => $attentionPoints,
            'general_guidance' => [[
                'text' => 'Realizar avaliações em condições semelhantes para favorecer comparação entre medições.',
                'source_chunk_ids' => ['ricosty-measurement-protocol'],
            ]],
            'professional_observation' => $observation,
            'prohibited_content_detected' => false,
        ];
    }

    private function validateResponse(array $response, BioimpedanceAssessment $assessment, Collection $chunks): array
    {
        $errors = [];
        $allowedChunkKeys = $chunks->pluck('chunk_key')->all();
        $text = Str::lower(json_encode($response, JSON_UNESCAPED_UNICODE));

        foreach (['diagnóstico', 'diagnostico', 'prescrevo', 'prescrição', 'medicamento', 'suplemento obrigatório', 'dieta de'] as $forbidden) {
            if (str_contains($text, $forbidden)) {
                $errors[] = 'Conteúdo proibido detectado: '.$forbidden;
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

        if ($response['prohibited_content_detected'] ?? false) {
            $errors[] = 'O agente sinalizou conteúdo proibido.';
        }

        return array_values(array_unique($errors));
    }

    private function retrieveApprovedChunks(array $context): Collection
    {
        $keys = collect(['ricosty-measurement-protocol', 'ricosty-assistant-safety-policy']);
        foreach (self::METRIC_CHUNKS as $metric => $chunkKey) {
            if (filled($context['metrics'][$metric]['classification'] ?? null)) {
                $keys->push($chunkKey);
            }
        }

        return BioimpedanceKnowledgeChunk::query()
            ->with('document')
            ->whereIn('chunk_key', $keys->unique()->values())
            ->where('language', 'pt-BR')
            ->whereHas('document', fn ($query) => $query->where('approved', true))
            ->get()
            ->sortBy(fn (BioimpedanceKnowledgeChunk $chunk) => $keys->search($chunk->chunk_key))
            ->values();
    }

    private function structuredContext(BioimpedanceAssessment $assessment, Collection $history): array
    {
        $indicators = $assessment->analysis['indicators'] ?? [];

        return [
            'assessment_id' => $assessment->id,
            'device_model' => $assessment->device_model,
            'reference_version' => $assessment->reference_version,
            'age_at_assessment' => $assessment->age_at_assessment,
            'biological_sex_at_assessment' => $assessment->biological_sex_at_assessment,
            'metrics' => [
                'weight' => ['value' => (float) $assessment->weight_kg, 'unit' => 'kg'],
                'bmi' => ['value' => (float) $assessment->calculated_bmi, 'unit' => 'kg/m²', 'classification' => $indicators['bmi']['classification'] ?? null, 'tone' => $indicators['bmi']['tone'] ?? null],
                'body_fat' => ['value' => $assessment->body_fat_percentage === null ? null : (float) $assessment->body_fat_percentage, 'unit' => '%', 'classification' => $indicators['body_fat']['classification'] ?? null, 'tone' => $indicators['body_fat']['tone'] ?? null],
                'skeletal_muscle' => ['value' => $assessment->skeletal_muscle_percentage === null ? null : (float) $assessment->skeletal_muscle_percentage, 'unit' => '%', 'classification' => $indicators['skeletal_muscle']['classification'] ?? null, 'tone' => $indicators['skeletal_muscle']['tone'] ?? null],
                'visceral_fat' => ['value' => $assessment->visceral_fat_level === null ? null : (float) $assessment->visceral_fat_level, 'unit' => 'nível', 'classification' => $indicators['visceral_fat']['classification'] ?? null, 'tone' => $indicators['visceral_fat']['tone'] ?? null],
                'resting_metabolism' => ['value' => $assessment->resting_metabolism_kcal, 'unit' => 'kcal/dia'],
                'body_age' => ['value' => $assessment->body_age, 'unit' => 'anos'],
            ],
            'previous_assessment' => $history->last() ? [
                'weight_kg' => (float) $history->last()->weight_kg,
                'body_fat_percentage' => $history->last()->body_fat_percentage === null ? null : (float) $history->last()->body_fat_percentage,
                'skeletal_muscle_percentage' => $history->last()->skeletal_muscle_percentage === null ? null : (float) $history->last()->skeletal_muscle_percentage,
                'visceral_fat_level' => $history->last()->visceral_fat_level === null ? null : (float) $history->last()->visceral_fat_level,
            ] : null,
            'warnings' => $assessment->analysis['warnings'] ?? [],
            'professional_notes' => $assessment->notes,
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
                    'source_chunk_ids' => [$chunkKey],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function classificationObservation(array $context): string
    {
        $metrics = $context['metrics'];
        $segments = [];
        foreach ([
            'body_fat' => 'gordura corporal',
            'skeletal_muscle' => 'músculo esquelético',
            'visceral_fat' => 'gordura visceral',
        ] as $key => $label) {
            if (filled($metrics[$key]['classification'] ?? null)) {
                $segments[] = $label.' '.mb_strtolower($metrics[$key]['classification']);
            }
        }

        $sentence = $segments
            ? 'A avaliação apresentou '.implode(', ', $segments).', conforme fontes aprovadas para interpretação.'
            : 'A avaliação foi registrada com os indicadores disponíveis.';

        if (filled($metrics['bmi']['classification'] ?? null)) {
            $sentence .= ' O IMC calculado foi de '.$this->number($metrics['bmi']['value'], 1).' kg/m², classificado como '.mb_strtolower($metrics['bmi']['classification']).'.';
        }

        return $sentence;
    }

    private function bodyAgeText(BioimpedanceAssessment $assessment): ?string
    {
        if ($assessment->body_age === null || $assessment->age_at_assessment === null) {
            return null;
        }

        $delta = $assessment->body_age - $assessment->age_at_assessment;
        if ($delta === 0) {
            return 'A idade corporal estimada ficou equivalente à idade cronológica.';
        }

        return 'A idade corporal estimada foi de '.$assessment->body_age.' anos, ficando '.abs($delta).' anos '.($delta > 0 ? 'acima' : 'abaixo').' da idade cronológica.';
    }

    private function variationText(BioimpedanceAssessment $assessment, ?array $previous): ?string
    {
        if (! $previous) {
            return 'Esta avaliação servirá como linha de base para comparação nas próximas medições.';
        }

        $changes = collect([
            $this->changeText('peso', $previous['weight_kg'], $assessment->weight_kg, 'kg', true),
            $this->changeText('gordura corporal', $previous['body_fat_percentage'], $assessment->body_fat_percentage, 'ponto percentual', true),
            $this->changeText('músculo esquelético', $previous['skeletal_muscle_percentage'], $assessment->skeletal_muscle_percentage, 'ponto percentual', false),
            $this->changeText('gordura visceral', $previous['visceral_fat_level'], $assessment->visceral_fat_level, 'nível', true, 0),
        ])->filter();

        return $changes->isEmpty() ? null : 'Em relação à avaliação anterior, houve '.$changes->implode(', ').'.';
    }

    private function changeText(string $label, mixed $previousValue, mixed $currentValue, string $unit, bool $lowerIsAttention, int $decimals = 1): ?string
    {
        if ($previousValue === null || $currentValue === null) {
            return null;
        }

        $delta = round((float) $currentValue - (float) $previousValue, $decimals);
        if ($delta == 0.0) {
            return 'manutenção de '.$label;
        }

        $direction = $delta > 0 ? 'aumento' : 'redução';
        $attention = ($lowerIsAttention && $delta > 0) || (! $lowerIsAttention && $delta < 0);

        return $direction.' de '.$this->number(abs($delta), $decimals).' '.$unit.($unit === 'nível' && abs($delta) != 1.0 ? 's' : '').' em '.$label.($attention ? ', ponto de atenção no acompanhamento' : '');
    }

    private function historyBefore(BioimpedanceAssessment $assessment): Collection
    {
        return BioimpedanceAssessment::query()
            ->where('bioimpedance_client_id', $assessment->bioimpedance_client_id)
            ->whereNull('canceled_at')
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
                    'approved' => true,
                    'approved_by_user_id' => $approvedBy?->id,
                    'approved_at' => now(),
                ]
            );

            if (! $document->approved) {
                $document->update([
                    'approved' => true,
                    'approved_by_user_id' => $approvedBy?->id,
                    'approved_at' => now(),
                ]);
            }

            foreach ($chunks as $chunkData) {
                BioimpedanceKnowledgeChunk::query()->updateOrCreate(
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
