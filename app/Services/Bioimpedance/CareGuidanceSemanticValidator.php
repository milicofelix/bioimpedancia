<?php

namespace App\Services\Bioimpedance;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CareGuidanceSemanticValidator
{
    public const CATEGORIES = [
        'assessment_context',
        'nutrition_education',
        'movement_education',
        'professional_follow_up',
        'safety_limit',
    ];

    public const CONFIDENCE_LEVELS = ['high', 'moderate', 'low'];

    private const FORBIDDEN_EXPRESSIONS = [
        'diagnóstico de',
        'prescrevo',
        'tome ',
        'dose de',
        'mg por',
        'colesterol alto',
        'diabetes',
        'hipertensão',
        'perda muscular confirmada',
        'apresenta perda muscular',
        'assimetria clínica identificada',
        'apresenta assimetria clínica',
        'comprova função muscular preservada',
        'confirma musculatura preservada',
    ];

    public function validate(array $response, Collection $chunks, array $context): array
    {
        $allowedSources = [
            ...$chunks->pluck('chunk_key')->all(),
            ...$this->availableInternalSources($context),
        ];
        $accepted = [];
        $blocked = [];
        $seenClaimIds = [];

        foreach (is_array($response['claims'] ?? null) ? $response['claims'] : [] as $index => $claim) {
            $normalized = $this->normalizeClaim($claim, $index);
            $reasons = $this->claimErrors($normalized, $allowedSources, $chunks);
            if (in_array($normalized['id'], $seenClaimIds, true)) {
                $reasons[] = 'Identificador de afirmação duplicado.';
            }
            $seenClaimIds[] = $normalized['id'];

            if ($reasons) {
                $blocked[] = [...$normalized, 'blocked_reasons' => $reasons];
            } else {
                $accepted[] = $normalized;
            }
        }

        $errors = [];
        if (! is_array($response['claims'] ?? null) || empty($response['claims'])) {
            $errors[] = 'A resposta não contém afirmações estruturadas.';
        }
        if (count(is_array($response['claims'] ?? null) ? $response['claims'] : []) > 10) {
            $errors[] = 'A resposta excede o limite de dez afirmações.';
        }
        if ($blocked) {
            $errors[] = count($blocked).' afirmação(ões) bloqueada(s) pela validação semântica.';
        }
        if (count($accepted) < 3) {
            $errors[] = 'A resposta não contém afirmações seguras suficientes.';
        }
        $acceptedTextLength = collect($accepted)->sum(fn (array $claim) => mb_strlen($claim['text']));
        if ($acceptedTextLength < 100) {
            $errors[] = 'O conjunto de orientações não possui conteúdo suficiente para revisão.';
        }
        if ($acceptedTextLength > 3500) {
            $errors[] = 'O conjunto de orientações excede o tamanho seguro para revisão.';
        }

        return [
            'accepted_claims' => $accepted,
            'blocked_claims' => $blocked,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function normalizeClaim(mixed $claim, int $index): array
    {
        if (! is_array($claim)) {
            return [
                'id' => 'claim-'.($index + 1),
                'category' => null,
                'text' => '',
                'confidence' => null,
                'requires_professional_review' => null,
                'source_ids' => [],
            ];
        }

        return [
            'id' => mb_substr(trim((string) ($claim['id'] ?? 'claim-'.($index + 1))), 0, 80),
            'category' => $claim['category'] ?? null,
            'text' => trim((string) ($claim['text'] ?? '')),
            'confidence' => $claim['confidence'] ?? null,
            'requires_professional_review' => $claim['requires_professional_review'] ?? null,
            'source_ids' => collect(is_array($claim['source_ids'] ?? null) ? $claim['source_ids'] : [])
                ->filter(fn ($source) => is_string($source) && filled($source))
                ->map(fn (string $source) => mb_substr(trim($source), 0, 160))
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function claimErrors(array $claim, array $allowedSources, Collection $chunks): array
    {
        $errors = [];
        if (! in_array($claim['category'], self::CATEGORIES, true)) {
            $errors[] = 'Categoria inválida.';
        }
        if (mb_strlen($claim['text']) < 20 || mb_strlen($claim['text']) > 900) {
            $errors[] = 'Texto fora do tamanho permitido.';
        }
        if (! in_array($claim['confidence'], self::CONFIDENCE_LEVELS, true)) {
            $errors[] = 'Nível de segurança inválido.';
        }
        if ($claim['requires_professional_review'] !== true) {
            $errors[] = 'A revisão profissional obrigatória não foi sinalizada.';
        }
        if (! $claim['source_ids']) {
            $errors[] = 'Afirmação sem fonte de sustentação.';
        }
        if (array_diff($claim['source_ids'], $allowedSources)) {
            $errors[] = 'Afirmação referencia fonte não aprovada ou indisponível.';
        }

        $requiredTag = match ($claim['category']) {
            'nutrition_education' => 'food',
            'movement_education' => 'physical_activity',
            'professional_follow_up', 'safety_limit' => 'safety',
            default => null,
        };
        if ($requiredTag && ! $chunks->whereIn('chunk_key', $claim['source_ids'])->contains(
            fn ($chunk) => in_array($requiredTag, $chunk->tags ?? [], true)
        )) {
            $errors[] = 'As fontes não sustentam a categoria informada.';
        }
        if ($claim['category'] === 'assessment_context'
            && ! collect($claim['source_ids'])->contains(fn (string $source) => str_starts_with($source, 'assessment:') || $source === 'observation:approved')) {
            $errors[] = 'Afirmação sobre a avaliação sem evidência interna.';
        }

        $lowerText = Str::lower($claim['text']);
        foreach (self::FORBIDDEN_EXPRESSIONS as $expression) {
            if (str_contains($lowerText, $expression)) {
                $errors[] = 'Conteúdo clínico não sustentado detectado pela barreira textual.';
                break;
            }
        }

        return array_values(array_unique($errors));
    }

    private function availableInternalSources(array $context): array
    {
        $sources = ['assessment:current'];
        if (! empty($context['history_changes'])) {
            $sources[] = 'assessment:history';
        }
        if (filled($context['professional_observation'] ?? null)) {
            $sources[] = 'observation:approved';
        }

        $sources = [...$sources, ...($context['segmental_evidence_ids'] ?? [])];

        return $sources;
    }
}
