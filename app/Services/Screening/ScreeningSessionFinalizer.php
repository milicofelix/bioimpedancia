<?php

namespace App\Services\Screening;

use App\Models\AppAudit;
use App\Models\Screening\ScreeningAnswer;
use App\Models\Screening\ScreeningSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreeningSessionFinalizer
{
    public function __construct(private readonly ScreeningScoringEngine $scoringEngine) {}

    /**
     * @param  list<array{question_id: int, option_id: int}>  $submittedAnswers
     */
    public function finalize(Request $request, ScreeningSession $session, array $submittedAnswers): ScreeningSession
    {
        return DB::transaction(function () use ($request, $session, $submittedAnswers): ScreeningSession {
            $lockedSession = ScreeningSession::query()
                ->with(['questionnaire.questions.options', 'questionnaire.ranges'])
                ->lockForUpdate()
                ->findOrFail($session->id);

            $this->assertSessionCanBeFinalized($lockedSession);
            $selectedOptions = $this->normalizeSubmittedAnswers($submittedAnswers);

            try {
                $score = $this->scoringEngine->score($lockedSession->questionnaire, $selectedOptions);
            } catch (\DomainException $exception) {
                throw ValidationException::withMessages(['answers' => $exception->getMessage()]);
            }

            foreach ($score->answers as $answer) {
                ScreeningAnswer::query()->create([
                    'screening_session_id' => $lockedSession->id,
                    'screening_question_id' => $answer['question_id'],
                    'screening_option_id' => $answer['option_id'],
                    'question_code' => $answer['question_code'],
                    'questionnaire_version' => $lockedSession->questionnaire_version,
                    'question_text_snapshot' => $answer['question_text'],
                    'response_label_snapshot' => $answer['response_label'],
                    'response_value' => $answer['response_value'],
                    'score' => $answer['score'],
                    'requires_individual_attention' => $answer['requires_individual_attention'],
                ]);
            }

            $lockedSession->update([
                'status' => ScreeningSession::STATUS_FINALIZED,
                'finalized_at' => now(),
                'total_score' => $score->total,
                'screening_range_id' => $score->rangeId,
                'result_description' => $score->rangeDescription,
                'result_message' => $score->rangeMessage,
                'scoring_rules_version' => $score->rulesVersion,
                'scoring_rules_snapshot' => $score->rulesSnapshot,
                'attention_question_codes' => $score->attentionQuestionCodes,
                'attention_notice_snapshot' => $score->attentionNotice,
            ]);

            AppAudit::query()->create([
                'user_id' => $request->user()?->id,
                'action' => 'screening.session.finalized',
                'auditable_type' => ScreeningSession::class,
                'auditable_id' => $lockedSession->id,
                'description' => 'Triagem pontuada e finalizada pelo motor determinístico',
                'new_values' => [
                    'questionnaire_code' => $lockedSession->questionnaire_code,
                    'questionnaire_version' => $lockedSession->questionnaire_version,
                    'total_score' => $score->total,
                    'result_description' => $score->rangeDescription,
                    'attention_question_codes' => $score->attentionQuestionCodes,
                    'attention_notice' => $score->attentionNotice,
                    'scoring_rules_version' => $score->rulesVersion,
                    'scoring_rules_snapshot' => $score->rulesSnapshot,
                    'calculation_source' => 'deterministic_internal_engine',
                    'uses_artificial_intelligence' => false,
                    'answers_count' => count($score->answers),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);

            return $lockedSession->refresh()->load(['answers', 'resultRange']);
        });
    }

    private function assertSessionCanBeFinalized(ScreeningSession $session): void
    {
        if ($session->status !== ScreeningSession::STATUS_STARTED) {
            throw ValidationException::withMessages(['session' => 'A aplicação precisa estar iniciada e ainda não finalizada.']);
        }

        if (! $session->identity_verified_at || ! $session->consent_accepted_at) {
            throw ValidationException::withMessages(['session' => 'A identidade e o consentimento precisam ser confirmados antes da finalização.']);
        }

        if ($session->revoked_at || ! $session->expires_at?->isFuture()) {
            throw ValidationException::withMessages(['session' => 'O convite expirou ou foi revogado antes da finalização.']);
        }

        if ($session->answers()->exists()) {
            throw ValidationException::withMessages(['session' => 'Esta aplicação já possui respostas registradas e não pode ser finalizada novamente.']);
        }
    }

    /**
     * @param  list<array{question_id: int, option_id: int}>  $submittedAnswers
     * @return array<int, int>
     */
    private function normalizeSubmittedAnswers(array $submittedAnswers): array
    {
        $selectedOptions = [];

        foreach ($submittedAnswers as $answer) {
            $questionId = filter_var($answer['question_id'] ?? null, FILTER_VALIDATE_INT);
            $optionId = filter_var($answer['option_id'] ?? null, FILTER_VALIDATE_INT);

            if (! $questionId || ! $optionId) {
                throw ValidationException::withMessages(['answers' => 'Cada resposta deve identificar uma pergunta e uma alternativa válidas.']);
            }

            if (array_key_exists($questionId, $selectedOptions)) {
                throw ValidationException::withMessages(['answers' => 'Cada pergunta deve possuir exatamente uma resposta.']);
            }

            $selectedOptions[$questionId] = $optionId;
        }

        return $selectedOptions;
    }
}
