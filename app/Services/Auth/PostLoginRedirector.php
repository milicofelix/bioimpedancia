<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

class PostLoginRedirector
{
    private const MOBILE_USER_AGENT_PATTERN = '/Mobile|iPhone|iPod|Android.+Mobile|Windows Phone|IEMobile|Opera Mini|BlackBerry|webOS/i';

    public function destination(Request $request): string
    {
        $intendedUrl = $request->session()->pull('url.intended');

        if (is_string($intendedUrl) && $intendedUrl !== '') {
            return $intendedUrl;
        }

        return $this->isMobile($request)
            ? route('operational-app')
            : route('dashboard');
    }

    private function isMobile(Request $request): bool
    {
        if ($request->header('Sec-CH-UA-Mobile') === '?1') {
            return true;
        }

        return preg_match(self::MOBILE_USER_AGENT_PATTERN, (string) $request->userAgent()) === 1;
    }
}
