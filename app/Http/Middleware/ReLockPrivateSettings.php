<?php

namespace App\Http\Middleware;

use App\Support\PrivateGallery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the Account settings Private panel open only for the current visit.
 * The private password forms are allowed to post without losing the token so
 * the panel can show its success message, but any other request re-locks it.
 */
class ReLockPrivateSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('private.password', 'private.reset')) {
            return $next($request);
        }

        if (! $request->routeIs('profile.private') || ! PrivateGallery::isSettingsUnlocked($request)) {
            PrivateGallery::lockSettings($request);
        }

        return $next($request);
    }
}
