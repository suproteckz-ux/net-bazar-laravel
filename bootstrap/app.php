<?php

use App\Http\Middleware\KaspiApiAuth;
use App\Http\Middleware\RedirectTechnicalDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Plesk/Nginx reverse-proxy headers so isSecure(), ip(), and URL
        // generation are correct when SSL terminates at the proxy layer.
        $middleware->trustProxies(at: '*');

        // Must run before sessions and CSRF so redirected requests never get
        // a session cookie on the technical domain.
        $middleware->web(prepend: [RedirectTechnicalDomain::class]);

        $middleware->alias([
            'kaspi.auth' => KaspiApiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
