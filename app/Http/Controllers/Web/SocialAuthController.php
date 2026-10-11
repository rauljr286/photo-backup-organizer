<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SocialAuthController extends Controller
{
    /**
     * Providers this app accepts. Facebook ships with Socialite; Google is
     * registered through the socialiteproviders package (see AppServiceProvider).
     */
    private const PROVIDERS = ['google', 'facebook'];

    /**
     * Send the visitor to the provider's consent screen.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        if (! $this->isConfigured($provider)) {
            return redirect()->route('login')->withErrors([
                'identifier' => ucfirst($provider).' sign-in is not available right now.',
            ]);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback: sign the matching account in, link the
     * provider to an existing account, or create a fresh one.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'identifier' => 'We could not sign you in with '.ucfirst($provider).'. Please try again.',
            ]);
        }

        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail() ? Str::lower((string) $socialUser->getEmail()) : null;

        $user = User::where('provider', $provider)->where('provider_id', $providerId)->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            // Link the provider to an existing (password) account the first
            // time the user signs in with it.
            if (! $user->provider) {
                $user->forceFill([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ])->save();
            }
        } else {
            $user = User::create([
                'name' => $this->displayName($socialUser, $email),
                'email' => $email,
                'provider' => $provider,
                'provider_id' => $providerId,
                'agreed_to_terms_at' => now(),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Best-effort display name from the provider profile, falling back to the
     * local part of the email address.
     */
    private function displayName(SocialiteUser $socialUser, ?string $email): string
    {
        $name = $socialUser->getName() ?: $socialUser->getNickname();

        return $name ?: Str::before((string) $email, '@') ?: 'New user';
    }

    /**
     * Whether OAuth credentials for the provider are present.
     */
    private function isConfigured(string $provider): bool
    {
        return filled(config("services.$provider.client_id"))
            && filled(config("services.$provider.client_secret"));
    }
}
