<?php

namespace App\Services\Screening;

use App\Models\AppAudit;
use App\Models\Screening\ScreeningQuestionnaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScreeningQuestionnaireApprovalService
{
    public function __construct(private readonly ScreeningScoringEngine $scoringEngine) {}

    public function approve(Request $request, ScreeningQuestionnaire $questionnaire, array $data): ScreeningQuestionnaire
    {
        return DB::transaction(function () use ($request, $questionnaire, $data): ScreeningQuestionnaire {
            $lockedQuestionnaire = ScreeningQuestionnaire::query()->lockForUpdate()->findOrFail($questionnaire->id);

            if ($lockedQuestionnaire->status === ScreeningQuestionnaire::STATUS_RETIRED) {
                throw ValidationException::withMessages(['questionnaire' => 'Uma versão descontinuada não pode ser reativada. Crie uma nova versão.']);
            }

            if ($lockedQuestionnaire->sessions()->exists()) {
                throw ValidationException::withMessages(['questionnaire' => 'Esta definição já possui aplicações e não pode receber uma nova aprovação editorial.']);
            }

            if ($lockedQuestionnaire->isAvailableForApplication()) {
                throw ValidationException::withMessages(['questionnaire' => 'Esta versão já está aprovada e ativa.']);
            }

            try {
                $this->scoringEngine->assertDefinitionIsDeterministic($lockedQuestionnaire);
            } catch (\DomainException $exception) {
                throw ValidationException::withMessages(['questionnaire' => $exception->getMessage()]);
            }

            $approvedAt = now();
            $attestations = [
                'recall_period_confirmed' => true,
                'questions_confirmed' => true,
                'ranges_confirmed' => true,
                'disclaimers_confirmed' => true,
                'electronic_signature_confirmed' => true,
                'approved_at' => $approvedAt->toIso8601String(),
            ];

            $lockedQuestionnaire->update([
                'status' => ScreeningQuestionnaire::STATUS_ACTIVE,
                'approved_by_user_id' => $request->user()->id,
                'approved_by_name' => $request->user()->name,
                'approved_profession' => $data['profession'],
                'professional_registration' => $data['professional_registration'],
                'approval_attestations' => $attestations,
                'approval_notes' => $data['notes'] ?? null,
                'approved_at' => $approvedAt,
            ]);

            AppAudit::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'screening.questionnaire.approved',
                'auditable_type' => ScreeningQuestionnaire::class,
                'auditable_id' => $lockedQuestionnaire->id,
                'description' => 'Versão do questionário aprovada e ativada para aplicação',
                'new_values' => [
                    'code' => $lockedQuestionnaire->code,
                    'version' => $lockedQuestionnaire->version,
                    'status' => $lockedQuestionnaire->status,
                    'approved_by_name' => $lockedQuestionnaire->approved_by_name,
                    'approved_profession' => $lockedQuestionnaire->approved_profession,
                    'professional_registration' => $lockedQuestionnaire->professional_registration,
                    'approval_attestations' => $attestations,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);

            return $lockedQuestionnaire->refresh();
        });
    }
}
