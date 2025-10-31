<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Log para detectar errores CSRF desde móviles
        $exceptions->report(function (TokenMismatchException $e) {
            $userAgent = request()->userAgent() ?? 'Unknown';
            $isMobile = preg_match('/(android|iphone|ipad|ipod)/i', $userAgent);

            Log::error('[LOGIN-MOBILE-DEBUG] TokenMismatchException capturada en Handler', [
                'timestamp' => now()->toDateTimeString(),
                'is_mobile' => $isMobile,
                'user_agent' => $userAgent,
                'ip' => request()->ip(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'session_id' => session()->getId(),
                'csrf_token_session' => session()->token(),
                'csrf_token_request' => request()->input('_token'),
                'referer' => request()->header('referer'),
                'cookies' => request()->cookie(),
            ]);
        });
    })->create();
