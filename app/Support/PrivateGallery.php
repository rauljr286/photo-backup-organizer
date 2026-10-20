<?php

namespace App\Support;

use Illuminate\Http\Request;

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
}
