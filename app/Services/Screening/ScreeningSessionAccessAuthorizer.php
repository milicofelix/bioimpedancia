<?php

namespace App\Services\Screening;

use App\Models\Screening\ScreeningSession;
use App\Models\User;
use Illuminate\Http\Request;

class ScreeningSessionAccessAuthorizer
{
    public function authorize(Request $request, ScreeningSession $session): User
    {
        $user = $request->user();
        abort_unless(
            $user?->isClinicalProfessional()
                && $user->isActive()
                && ($user->isAdmin() || $session->requested_by_user_id === $user->id),
            403,
        );
        abort_if($session->anonymized_at || ! $session->bioimpedance_client_id, 410, 'Esta triagem foi anonimizada conforme a política de retenção.');

        return $user;
    }
}
