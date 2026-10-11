<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogoutListener
{
    public function __construct(private Request $request)
    {
        //
    }

    public function handle(Logout $event): void
    {
        try {
            LoginLog::create([
                'user_id' => $event->user?->id,
                'event' => 'logout',
                'identifier' => null,
                'ip_address' => $this->getIp(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log logout event', ['error' => $e->getMessage()]);
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
}
