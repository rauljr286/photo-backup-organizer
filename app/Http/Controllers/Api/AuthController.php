<?php

namespace App\Http\Controllers\Api;

use App\HasPhoneNormalization;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        protected PhotoStorageService $photos,
    ) {}

    /**
     * Register a new account and return a token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email ? strtolower((string) $request->email) : null,
            'password' => $request->password,
        ]);

        $token = $user->createToken('api-auth')->plainTextToken;

        $this->recordLoginLog($request, 'login', $user->id, $this->sanitizeIdentifierForLog($user->email ?: $user->phone));

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * Authenticate and return a token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = (string) $request->input('identifier', $request->input('email', ''));
        $password = (string) $request->input('password');
        $user = null;
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', strtolower($identifier))->first();
        } else {
            $normalized = HasPhoneNormalization::normalizePhone($identifier);
            $user = User::where('phone', $normalized)->first();
            if (! $user && $identifier !== $normalized) {
                $user = User::where('phone', $identifier)->first();
            }
        }

        if ($user === null || ! Hash::check($password, $user->password)) {
            $sanitized = $this->sanitizeIdentifierForLog($identifier);

            $this->recordLoginLog(
                $request,
                'failed',
                $user?->id ?? $this->lookupUserId($sanitized),
                $sanitized,
            );

            throw ValidationException::withMessages([
                'credentials' => ['The email address or password is incorrect.'],
            ]);
        }

        $this->recordLoginLog($request, 'login', $user->id, $this->sanitizeIdentifierForLog($identifier));

        $token = $user->createToken('api-auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Invalidate the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->recordLoginLog($request, 'logout', $request->user()?->id, null);

        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Signed out successfully.']);
    }

    /**
     * The authenticated user's profile and storage usage.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    /**
     * Build the JSON payload returned by the auth endpoints.
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatarUrl(),
            'storage' => [
                'used_bytes' => $user->storageUsedBytes(),
                'limit_bytes' => $user->storageLimitBytes(),
                'used_percent' => $user->storageUsedPercent(),
                'limit_mb' => $user->storage_limit_mb,
            ],
            'photo_count' => $user->photos()->count(),
            'album_count' => $user->albums()->count(),
        ];
    }

    /**
     * Persist a login activity entry without ever letting a logging failure
     * interrupt authentication.
     */
    private function recordLoginLog(Request $request, string $event, ?int $userId, ?string $identifier): void
    {
        try {
            LoginLog::create([
                'user_id' => $userId,
                'event' => $event,
                'identifier' => $identifier,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log '.$event.' event', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Resolve the account id for an identifier that looks like an email or a
     * Philippine mobile number, or null when nothing matches.
     */
    private function lookupUserId(?string $identifier): ?int
    {
        if (! $identifier) {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', Str::lower($identifier))->value('id');
        }

        $normalized = HasPhoneNormalization::normalizePhone($identifier);
        $id = User::where('phone', $normalized)->value('id');

        if (! $id && $identifier !== $normalized) {
            $id = User::where('phone', $identifier)->value('id');
        }

        return $id;
    }

    /**
     * The identifier field is captured verbatim, but only when it actually
     * looks like an email address or a Philippine mobile number. Anything else
     * (for example a password typed into the wrong box) is stored as null.
     */
    private function sanitizeIdentifierForLog(?string $identifier): ?string
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
