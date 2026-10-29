<?php

namespace App\Services\Screening;

use App\Models\Screening\ScreeningSession;
use Illuminate\Support\Collection;

class ScreeningHistoryPresenter
{
    /** @return list<array<string, mixed>> */
    public function present(Collection $sessions): array
    {
        $chronological = $sessions->sortBy('created_at')->values();
        $finalizedSeen = collect();
        $comparisons = [];

        foreach ($chronological as $session) {
            $comparisons[$session->id] = $this->comparisonFor($session, $finalizedSeen);

            if ($session->status === ScreeningSession::STATUS_FINALIZED) {
                $finalizedSeen->push($session);
            }
        }

        return $sessions->sortByDesc('created_at')->values()
            ->map(fn (ScreeningSession $session): array => [
                'id' => $session->id,
                'questionnaire_code' => $session->questionnaire_code,
                'questionnaire_name' => $session->questionnaire_name,
                'questionnaire_version' => $session->questionnaire_version,
                'status' => $session->status,
                'access_mode' => $session->access_mode,
                'requested_by_name' => $session->requested_by_name,
                'created_at' => $session->created_at?->toIso8601String(),
                'started_at' => $session->started_at?->toIso8601String(),
                'finalized_at' => $session->finalized_at?->toIso8601String(),
                'expires_at' => $session->expires_at?->toIso8601String(),
                'revoked_at' => $session->revoked_at?->toIso8601String(),
                'send_count' => $session->send_count,
                'total_score' => $session->total_score,
                'result_description' => $session->result_description,
                'document_authenticity_code' => $session->document_authenticity_code,
                'comparison' => $comparisons[$session->id],
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function comparisonFor(ScreeningSession $current, Collection $finalizedSeen): array
    {
        if ($current->status !== ScreeningSession::STATUS_FINALIZED) {
            return ['state' => 'unavailable', 'message' => 'A comparação ficará disponível após a conclusão.'];
        }

        $previousApplication = $finalizedSeen->last();
        if (! $previousApplication) {
            return ['state' => 'baseline', 'message' => 'Primeira aplicação finalizada desta versão: linha de base descritiva.'];
        }

        $sameVersion = $previousApplication->questionnaire_code === $current->questionnaire_code
            && $previousApplication->questionnaire_version === $current->questionnaire_version;

        if ($sameVersion) {
            $delta = $current->total_score - $previousApplication->total_score;
            $direction = $delta === 0 ? 'stable' : ($delta > 0 ? 'increase' : 'decrease');
            $movement = $delta === 0
                ? 'A pontuação permaneceu igual.'
                : 'A pontuação '.($delta > 0 ? 'aumentou' : 'diminuiu').' '.abs($delta).' ponto(s).';

            return [
                'state' => 'comparable',
                'direction' => $direction,
                'previous_session_id' => $previousApplication->id,
                'previous_score' => $previousApplication->total_score,
                'current_score' => $current->total_score,
                'delta' => $delta,
                'previous_finalized_at' => $previousApplication->finalized_at?->toIso8601String(),
                'message' => $movement.' Esta é uma comparação descritiva e não representa melhora ou piora clínica.',
            ];
        }

        return [
            'state' => 'incompatible_version',
            'previous_version' => $previousApplication->questionnaire_version,
            'current_version' => $current->questionnaire_version,
            'message' => 'A aplicação anterior usa outra versão. As pontuações não devem ser comparadas diretamente.',
        ];
    }
}
