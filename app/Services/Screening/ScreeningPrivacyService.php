<?php

namespace App\Services\Screening;

use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Screening\ScreeningSession;
use App\Models\Screening\ScreeningSessionReview;
use Illuminate\Support\Facades\DB;

class ScreeningPrivacyService
{
    public function anonymizeClientScreenings(BioimpedanceClient $client, string $reason = 'client_anonymization'): int
    {
        $sessionIds = ScreeningSession::query()
            ->where('bioimpedance_client_id', $client->id)
            ->pluck('id');

        foreach ($sessionIds as $sessionId) {
            $this->anonymizeSession((int) $sessionId, $reason);
        }

        return $sessionIds->count();
    }

    public function applyExpiredRetention(): int
    {
        $processed = 0;

        ScreeningSession::query()
            ->whereNull('anonymized_at')
            ->whereNotNull('bioimpedance_client_id')
            ->whereNotNull('retention_expires_at')
            ->where('retention_expires_at', '<=', now())
            ->whereIn('status', [ScreeningSession::STATUS_FINALIZED, ScreeningSession::STATUS_CANCELLED])
            ->orderBy('id')
            ->eachById(function (ScreeningSession $session) use (&$processed): void {
                $this->anonymizeSession($session->id, 'retention_expired');
                $processed++;
            });

        return $processed;
    }

    private function anonymizeSession(int $sessionId, string $reason): void
    {
        DB::transaction(function () use ($sessionId, $reason): void {
            $session = DB::table('screening_sessions')->where('id', $sessionId)->lockForUpdate()->first();
            if (! $session || $session->anonymized_at !== null) {
                return;
            }

            ScreeningSessionReview::query()
                ->where('screening_session_id', $sessionId)
                ->update(['professional_notes' => null, 'referral_notes' => null]);

            AppAudit::query()
                ->where('auditable_type', ScreeningSession::class)
                ->where('auditable_id', $sessionId)
                ->update([
                    'description' => 'Evento preservado sem conteúdo pessoal após anonimização LGPD.',
                    'old_values' => null,
                    'new_values' => json_encode(['anonymized' => true], JSON_THROW_ON_ERROR),
                    'ip_address' => null,
                    'user_agent' => null,
                ]);

            DB::table('screening_sessions')->where('id', $sessionId)->update([
                'bioimpedance_client_id' => null,
                'public_token_hash' => null,
                'guardian_name' => null,
                'guardian_relationship' => null,
                'guardian_consent_text_snapshot' => null,
                'anonymized_at' => now(),
                'updated_at' => now(),
            ]);

            AppAudit::query()->create([
                'user_id' => null,
                'action' => 'screening.session.anonymized',
                'auditable_type' => ScreeningSession::class,
                'auditable_id' => $sessionId,
                'description' => 'Triagem desvinculada do cliente conforme política LGPD',
                'new_values' => ['reason' => $reason, 'anonymized' => true],
                'ip_address' => null,
                'user_agent' => null,
            ]);
        });
    }
}
