<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceReportShare;

class BioimpedanceReportSharePresenter
{
    public function payload(BioimpedanceReportShare $share, ?string $plainToken = null): array
    {
        $url = $plainToken ? route('bioimpedance.public-report', $plainToken) : null;
        $message = $plainToken ? $this->message($share->assessment, $plainToken) : $share->message;

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
            'is_active' => ! $share->revoked_at && ! $share->expires_at->isPast(),
        ];
    }

    public function message(BioimpedanceAssessment $assessment, ?string $plainToken = null): string
    {
        $url = $plainToken ? route('bioimpedance.public-report', $plainToken) : '[link temporário enviado somente no momento da geração]';

        return sprintf(
            'Olá, %s! Sua avaliação de bioimpedância realizada em %s está disponível. Acesse o relatório pelo link: %s',
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
