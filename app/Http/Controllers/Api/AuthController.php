<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $token = $user->createToken('api-auth')->plainTextToken;

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
        $user = User::where('email', $request->email)->first();

        if ($user === null || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'credentials' => ['The email address or password is incorrect.'],
            ]);
        }

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
}
