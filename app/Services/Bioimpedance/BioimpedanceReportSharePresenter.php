<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceReportShare;
use Throwable;

class BioimpedanceReportSharePresenter
{
    public function payload(BioimpedanceReportShare $share, ?string $plainToken = null, bool $includeAccess = true): array
    {
        $isActive = ! $share->revoked_at && ! $share->expires_at->isPast();
        $accessToken = $plainToken ?? ($includeAccess && $isActive ? $this->recoverableToken($share) : null);
        $url = $accessToken ? route('bioimpedance.public-report', $accessToken) : null;
        $message = $accessToken ? $this->message($share->assessment, $accessToken) : $share->message;

        return [
            'id' => $share->id,
            'bioimpedance_assessment_id' => $share->bioimpedance_assessment_id,
            'channel' => $share->channel,
            'recipient' => $share->recipient,
            'message' => $message,
            'url' => $url,
            'whatsapp_url' => $url ? 'https://wa.me/?text='.rawurlencode($message) : null,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'revoked_at' => $share->revoked_at?->toIso8601String(),
            'viewed_at' => $share->viewed_at?->toIso8601String(),
            'view_count' => $share->view_count,
            'is_active' => $isActive,
            'has_recoverable_url' => $url !== null,
        ];
    }

    private function recoverableToken(BioimpedanceReportShare $share): ?string
    {
        try {
            return filled($share->encrypted_token) ? $share->encrypted_token : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function message(BioimpedanceAssessment $assessment, ?string $plainToken = null): string
    {
        $url = $plainToken ? route('bioimpedance.public-report', $plainToken) : '[link temporário protegido]';

        return sprintf(
            'Olá, %s! Sua avaliação de bioimpedância realizada em %s está disponível em PDF. Acesse o relatório pelo link: %s',
            $assessment->client->full_name,
            $assessment->evaluated_at->format('d/m/Y'),
            $url
        );
    }

    public function publicReportHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
