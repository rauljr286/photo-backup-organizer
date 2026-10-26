<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tracks whether the private gallery is currently unlocked for the signed-in
 * session. The private gallery has its own password, separate from the account
 * password; unlocking is remembered for the session and cleared on sign-out
 * (the session is invalidated on logout).
 */
class PrivateGallery
{
    /**
     * Session key holding the unlock flag.
     */
    public const SESSION_KEY = 'private_unlocked';

    /**
     * Session key holding the one-visit token for the Account settings panel.
     * This is deliberately separate from the gallery unlock flag.
     */
    public const SETTINGS_SESSION_KEY = 'private_settings_token';

    /**
     * Whether the private gallery is unlocked for this session.
     */
    public static function isUnlocked(Request $request): bool
    {
        return $request->session()->get(self::SESSION_KEY) === true;
    }

    /**
     * Mark the private gallery as unlocked for this session.
     */
    public static function unlock(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, true);
    }

    /**
     * Re-lock the private gallery for this session.
     */
    public static function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Allow the Account settings Private panel for one visit. The returned
     * token is placed in the redirect URL and must match the session token.
     */
    public static function unlockSettings(Request $request): string
    {
        $token = Str::random(40);

        $request->session()->put(self::SETTINGS_SESSION_KEY, $token);

        return $token;
    }

    /**
     * Whether this request carries the token for the current settings visit.
     */
    public static function isSettingsUnlocked(Request $request): bool
    {
        $stored = $request->session()->get(self::SETTINGS_SESSION_KEY);
        $given = $request->query('private_unlock');

        return is_string($stored)
            && is_string($given)
            && hash_equals($stored, $given);
    }

    /**
     * Re-lock the Account settings Private panel.
     */
    public static function lockSettings(Request $request): void
    {
        $request->session()->forget(self::SETTINGS_SESSION_KEY);
    }
}
