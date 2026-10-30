<?php

namespace App\Http\Middleware;

use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClinicModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $settings = BioimpedanceClinicSetting::current();

        abort_unless(
            $settings->moduleEnabled($module),
            Response::HTTP_FORBIDDEN,
            'Este módulo está desativado nas configurações da clínica.',
        );

        return $next($request);
    }
}
