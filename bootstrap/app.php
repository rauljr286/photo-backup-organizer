<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app runs behind the Railway HTTP proxy, which forwards the
        // original client IP, scheme and host in its request headers. Trust all
        // proxies here so redirects, URLs, sessions and request()->ip() reflect
        // the real client instead of the proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    // Login activity listeners are registered explicitly in AppServiceProvider.
    // Disable app/Listeners auto-discovery so each authentication event is
    // handled exactly once (otherwise "login" is written twice per sign-in).
    ->withEvents(discover: false)
    ->create();
