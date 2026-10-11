<?php

namespace App\Listeners;

use App\HasPhoneNormalization;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginFailedListener
{
    public function __construct(private Request $request)
    {
        //
    }

    public function handle(Failed $event): void
    {
        try {
            $rawIdentifier = (string) $this->request->input('identifier', $this->request->input('email', ''));
            $sanitized = $this->sanitizeIdentifier($rawIdentifier);
            $userId = $event->user?->id;

            if (! $userId && $sanitized) {
                $user = null;
                if (filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
                    $user = User::where('email', Str::lower($sanitized))->first();
                } else {
                    $normalized = HasPhoneNormalization::normalizePhone($sanitized);
                    $user = User::where('phone', $normalized)->first();
                    if (! $user && $sanitized !== $normalized) {
                        $user = User::where('phone', $sanitized)->first();
                    }
                }
                if ($user) {
                    $userId = $user->id;
                }
            }

            LoginLog::create([
                'user_id' => $userId,
                'event' => 'failed',
                'identifier' => $sanitized,
                'ip_address' => $this->getIp(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log failed login event', ['error' => $e->getMessage()]);
        }
    }

    private function getIp(): ?string
    {
        $ip = $this->request->ip();
        if (! is_string($ip)) {
            return null;
        }

        return $ip;
    }

    private function sanitizeIdentifier(?string $identifier): ?string
    {
        if (! $identifier) {
            return null;
        }
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $identifier;
        }
        if (preg_match('/^09\d{9}$/', $identifier)) {
            return $identifier;
        }
        if (preg_match('/^\+639\d{9}$/', $identifier)) {
            return $identifier;
        }

        return null;
    }
}
