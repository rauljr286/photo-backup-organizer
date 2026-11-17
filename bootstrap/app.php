<?php

use App\Http\Middleware\ReLockPrivateSettings;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');

        if (filled($trustedProxies)) {
            $middleware->trustProxies(at: array_map('trim', explode(',', (string) $trustedProxies)));
        }

        // Invalidate sessions whose stored password hash no longer matches the
        // user's current password. This makes "log out other sessions" and
        // password changes take effect on every other device.
        $middleware->web(append: [
            AuthenticateSession::class,
            ReLockPrivateSettings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    // Login activity listeners are registered explicitly in AppServiceProvider.
    // Disable app/Listeners auto-discovery so each authentication event is
    // handled exactly once (otherwise "login" is written twice per sign-in).
    ->withEvents(discover: false)
    ->create();
