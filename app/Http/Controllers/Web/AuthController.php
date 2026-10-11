<?php

namespace App\Http\Controllers\Web;

use App\HasPhoneNormalization;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\MethodNotAllowedException;
use Illuminate\Routing\Exceptions\NotFoundHttpException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Routes that answer with a file instead of a page. Reaching one of them
     * is almost always the tail of a sub-resource request, so it is never a
     * sensible place to drop a user who has just signed in.
     */
    private const FILE_ROUTES = [
        'photos.thumbnail',
        'photos.raw',
        'photos.download',
        'albums.download',
    ];

    /**
     * Show the login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate the user against the session guard.
     *
     * Failed attempts are rate-limited per email+IP combination (5 per minute)
     * so the login form cannot be sprayed. A successful login clears the count
     * for that key, so legit users are never locked out.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $limiter = app(RateLimiter::class);
        $key = $this->throttleKey($request, 'login');

        if ($limiter->tooManyAttempts($key, 5)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'identifier' => 'Too many login attempts. Please try again in '.$limiter->availableIn($key).' seconds.',
            ]);
        }

        $identifier = (string) $request->input('identifier');
        $password = (string) $request->input('password');
        $remember = (bool) $request->boolean('remember');

        $user = null;
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', Str::lower($identifier))->first();
        } else {
            $normalizedPhone = HasPhoneNormalization::normalizePhone($identifier);
            $user = User::where('phone', $normalizedPhone)->first();
            if (! $user && $identifier !== $normalizedPhone) {
                $user = User::where('phone', $identifier)->first();
            }
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            $limiter->hit($key, 60);

            event(new Failed('web', $user, []));

            throw ValidationException::withMessages([
                'identifier' => 'The email address or mobile number and password you entered is incorrect.',
            ]);
        }

        Auth::login($user, $remember);
        $limiter->clear($key);
        $request->session()->regenerate();

        return redirect()->to($this->intendedUrl($request));
    }

    /**
     * Where a freshly signed-in user should land.
     *
     * The `auth` middleware remembers the page a guest asked for, which is how
     * someone who clicked "Albums" ends up back on /albums after signing in.
     * The thumbnail, raw and download routes sit behind that same middleware,
     * so a browser loading an image while signed out can leave a bare file in
     * there — landing on it straight after signing in is never useful, so those
     * fall back to the dashboard. Anything that is not a real page in this app
     * falls back too, which also keeps the redirect on this host.
     */
    private function intendedUrl(Request $request): string
    {
        $dashboard = route('dashboard');
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || trim($intended) === '') {
            return $dashboard;
        }

        $target = parse_url($intended);

        if ($target === false || (isset($target['host']) && $target['host'] !== $request->getHost())) {
            return $dashboard;
        }

        $path = '/'.ltrim($target['path'] ?? '/', '/');
        $destination = $path.((isset($target['query']) && $target['query'] !== '') ? '?'.$target['query'] : '');

        try {
            $route = app('router')->getRoutes()->match(Request::create($destination, 'GET'));
        } catch (MethodNotAllowedException|NotFoundHttpException $exception) {
            return $dashboard;
        }

        if (in_array($route->getName(), self::FILE_ROUTES, true)) {
            return $dashboard;
        }

        return $destination;
    }

    /**
     * Show the registration form. Unless the visitor has already agreed to
     * the Terms of Service and Privacy Policy during this session, only the
     * mandatory legal agreement step is shown first.
     */
    public function showRegister(Request $request): View
    {
        return view('auth.register', [
            'agreed' => $request->session()->has('agreed_to_terms_at'),
            'supportEmail' => config('photobackup.support_email', 'support@example.com'),
        ]);
    }

    /**
     * Record in the session that the visitor read and agreed to both the
     * Terms of Service and the Privacy Policy, then reveal the registration
     * form. No account is created here.
     */
    public function agreeToTerms(Request $request): RedirectResponse
    {
        $request->session()->put('agreed_to_terms_at', now());

        return redirect()->route('register')->with('status', 'You have reviewed and agreed to the Terms and Conditions and the Privacy Policy.');
    }

    /**
     * Register a new account and sign the user in. Registration is blocked
     * unless the visitor first completed the agreement step in this session.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        if (! $request->session()->has('agreed_to_terms_at')) {
            throw ValidationException::withMessages([
                'agree' => 'Please review and agree to the Terms and Conditions and the Privacy Policy before creating your account.',
            ]);
        }

        $email = $request->input('email');
        $phone = $request->input('phone');

        $user = User::create([
            'name' => $request->name,
            'email' => $email ? Str::lower($email) : null,
            'phone' => HasPhoneNormalization::normalizePhone($phone),
            'password' => Hash::make((string) $request->input('password')),
            'agreed_to_terms_at' => $request->session()->get('agreed_to_terms_at'),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Sign the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        // Logging handled by the Logout event listener.

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Cache key (scope + email + IP) used for throttling login requests.
     */
    protected function throttleKey(Request $request, string $scope): string
    {
        $identifier = (string) $request->input('identifier', $request->input('email', ''));
        $identifier = Str::lower($identifier);

        return 'auth-'.$scope.':'.$identifier.'|'.$request->ip();
    }

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
            return Str::lower($identifier);
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
