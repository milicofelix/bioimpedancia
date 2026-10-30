<?php

use App\Http\Middleware\EnsureHttps;
use App\Http\Middleware\EnsureClinicModuleEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return tap(Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'clinic.module' => EnsureClinicModuleEnabled::class,
        ]);

        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        $middleware->append(EnsureHttps::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create(), function (Application $app): void {
        $app->usePublicPath(dirname(__DIR__).'/public_html');
    });
