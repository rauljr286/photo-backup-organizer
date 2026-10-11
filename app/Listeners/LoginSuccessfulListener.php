<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginSuccessfulListener
{
    public function __construct(private Request $request)
    {
        //
    }

    public function handle(Login $event): void
    {
        try {
            $identifier = $this->sanitizeIdentifier(
                (string) $this->request->input('identifier', $this->request->input('email', ''))
            );

            LoginLog::create([
                'user_id' => $event->user?->id,
                'event' => 'login',
                'identifier' => $identifier,
                'ip_address' => $this->getIp(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log login event', ['error' => $e->getMessage()]);
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
        // Check for PH mobile number
        if (preg_match('/^09\d{9}$/', $identifier)) {
            return $identifier;
        }
        if (preg_match('/^\+639\d{9}$/', $identifier)) {
            return $identifier;
        }

        return null;
    }
}
