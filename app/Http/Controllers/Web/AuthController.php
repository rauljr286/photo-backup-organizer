<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\PasswordResetLink;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in '.$limiter->availableIn($key).' seconds.',
            ]);
        }

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            $limiter->hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'The email address or password you entered is incorrect.',
            ]);
        }

        $limiter->clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
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
            'retentionDays' => (int) config('photobackup.trash_retention_days', 30),
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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
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
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Show the "forgot password" form.
     */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link.
     *
     * Requests are rate-limited per email+IP so this endpoint cannot be abused
     * to spam the reset mailer. The count is intentionally not cleared on
     * success: the short cooldown simply protects the form from automated abuse.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $limiter = app(RateLimiter::class);
        $key = $this->throttleKey($request, 'forgot-password');

        if ($limiter->tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many reset requests. Please try again in '.$limiter->availableIn($key).' seconds.',
            ]);
        }

        $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user === null) {
            $limiter->hit($key, 60);

            return back()->with('status', 'If an account exists for that email, a reset link has been sent.');
        }

        $token = Password::broker()->createToken($user);

        $limiter->hit($key, 60);

        Mail::to($user)->send(new PasswordResetLink($user, $token));

        if (app()->environment('local')) {
            // Development convenience: mail is logged by default, so also surface the link.
            $link = route('password.reset', ['token' => $token, 'email' => $user->email]);

            return back()->with('status', 'Development mode: a reset link was generated — '.$link);
        }

        return back()->with('status', 'If an account exists for that email, a reset link has been sent.');
    }

    /**
     * Cache key (scope + email + IP) used for throttling login and password
     * reset requests.
     */
    protected function throttleKey(Request $request, string $scope): string
    {
        $email = Str::lower((string) $request->string('email'));

        return 'auth-'.$scope.':'.$email.'|'.$request->ip();
    }

    /**
     * Show the password reset form.
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please choose a new password.',
            'password.min' => 'Your password must be at least 8 characters long.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();

                event(new PasswordReset($user));
                Auth::login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $request->session()->regenerate();

            return redirect()->route('dashboard')->with('status', 'Your password has been reset and you are signed in.');
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }
}
