@props([
    'state' => 'login',
    'agreed' => false,
    'supportEmail' => null,
])

@php
    $isRegister = $state === 'register';

    // Errors that are rendered inline next to their own field. Anything else
    // (e.g. a cross-field rule) falls back to a summary at the top of the card.
    $inlineErrorKeys = [
        'name', 'first_name', 'middle_name', 'last_name', 'suffix',
        'email', 'phone',
        'password', 'password_confirmation',
        'identifier', 'agree',
    ];
    $summaryErrors = collect($errors->keys())
        ->reject(fn ($key) => in_array($key, $inlineErrorKeys, true))
        ->flatMap(fn ($key) => $errors->get($key));

    $userIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    $mailIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m3 7 9 6 9-6"/></svg>';
    $phoneIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
    $lockIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3.5" y="10.5" width="17" height="10.5" rx="2.5"/><path d="M7.5 10.5V7a4.5 4.5 0 0 1 9 0v3.5"/></svg>';
    $googleIcon = '<svg class="auth-card__social-icon" viewBox="0 0 48 48" width="18" height="18" aria-hidden="true" focusable="false"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
    $facebookIcon = '<svg class="auth-card__social-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.95.93-1.95 1.89v2.25h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07z"/></svg>';

    // Only advertise social providers whose OAuth credentials are present, so
    // an unconfigured provider never leaves a dead-end button on the card.
    $socialProviders = collect([
        'google' => ['label' => 'Continue with Google', 'icon' => $googleIcon],
        'facebook' => ['label' => 'Continue with Facebook', 'icon' => $facebookIcon],
    ])->filter(fn ($provider, $name) => filled(config("services.$name.client_id"))
        && filled(config("services.$name.client_secret")));
@endphp

<div class="auth-card" data-auth-card data-state="{{ $isRegister ? 'register' : 'login' }}"
     data-login-url="{{ route('login') }}" data-register-url="{{ route('register') }}">

    {{-- ---------- Sign-in pane ---------- --}}
    <section
        class="auth-card__pane auth-card__pane--signin"
        data-auth-pane="login"
        aria-labelledby="auth-signin-heading"
        @if ($isRegister) inert @endif
    >
        <div class="auth-card__pane-inner" data-auth-stagger>
            <h1 class="auth-card__heading" id="auth-signin-heading">Sign in</h1>
            <p class="auth-card__lead">Welcome back. Sign in to reach your library.</p>

            @if ($summaryErrors->isNotEmpty())
                <div class="auth-card__summary" role="alert">
                    @foreach ($summaryErrors as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            @if ($socialProviders->isNotEmpty())
                <div class="auth-card__social">
                    @foreach ($socialProviders as $name => $provider)
                        <a class="btn btn--social" href="{{ route('social.redirect', $name) }}" rel="nofollow">
                            {!! $provider['icon'] !!}
                            <span>{{ $provider['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                <p class="auth-card__consent">
                    By continuing you agree to our
                    <a class="link" href="{{ route('terms') }}">Terms</a> and
                    <a class="link" href="{{ route('privacy') }}">Privacy Policy</a>.
                </p>

                <p class="auth-card__divider"><span>or</span></p>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="form" novalidate id="login-form">
                @csrf
                <input type="hidden" name="_auth_pane" value="login">

                <div class="field">
                    <label class="field__label" for="identifier">Email or mobile number</label>
                    <div class="field__control">
                        <span class="field__icon">{!! $userIcon !!}</span>
                        <input class="field__input" id="identifier" type="text" name="identifier"
                               value="{{ old('identifier') }}" required autocomplete="username"
                               @error('identifier') aria-invalid="true" aria-describedby="identifier-error" @enderror>
                    </div>
                    @error('identifier')
                        <p class="field__error" id="identifier-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
                    <div class="field__password field__control">
                        <span class="field__icon">{!! $lockIcon !!}</span>
                        <input class="field__input" id="password" type="password" name="password" required autocomplete="current-password">
                    </div>
                </div>

                <div class="field field--row">
                    <label class="field__check">
                        <input class="field__checkbox" type="checkbox" name="remember" value="1">
                        <span>Keep me signed in</span>
                    </label>
                </div>

                <button type="submit" class="btn btn--primary btn--block" data-loading-label="Signing in...">Sign in</button>
            </form>
        </div>
    </section>

    {{-- ---------- Create-account pane ---------- --}}
    <section
        class="auth-card__pane auth-card__pane--signup"
        data-auth-pane="register"
        aria-labelledby="auth-signup-heading"
        @if (! $isRegister) inert @endif
    >
        <div class="auth-card__pane-inner" data-auth-stagger>
            @error('agree')
                <div class="auth-card__summary" role="alert">
                    <p>{{ $message }}</p>
                </div>
            @enderror

            @if (! $agreed)
                <section class="auth-legal">
                    <h1 class="auth-card__heading" id="register-agreement-title">Create your account</h1>
                    <p class="auth-card__lead">
                        Please review our Terms and Privacy Policy. Scroll to the end to unlock the
                        <strong>I Agree</strong> button.
                    </p>

                    <div class="auth-legal__progress" role="progressbar" aria-label="Terms reading progress"
                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-terms-progress>
                        <span class="auth-legal__progress-bar" data-terms-progress-bar></span>
                    </div>

                    <div class="auth-legal__box" tabindex="0" role="region"
                         aria-label="Terms and Conditions and Privacy Policy"
                         aria-describedby="register-agreement-title" data-terms-scroll>
                        <section class="legal auth-legal__section" aria-labelledby="register-terms-heading">
                            <h2 id="register-terms-heading">Terms and Conditions</h2>
                            <p>By using this app, you agree to the following:</p>
                            @include('legal.partials.terms-content', ['supportEmail' => $supportEmail])
                        </section>

                        <section class="legal auth-legal__section" aria-labelledby="register-privacy-heading">
                            <h2 id="register-privacy-heading">Privacy Policy</h2>
                            @include('legal.partials.privacy-content', ['supportEmail' => $supportEmail])
                        </section>
                    </div>

                    <p class="auth-legal__hint" data-terms-hint>
                        Keep scrolling &mdash; the button unlocks at the end.
                    </p>

                    <form method="POST" action="{{ route('register.agree') }}" class="auth-legal__actions">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--block" data-terms-agree>I Agree</button>
                    </form>
                </section>
            @else
                <h1 class="auth-card__heading" id="auth-signup-heading">Create account</h1>
                <p class="auth-card__lead">Your photos are backed up privately. Only you can see them.</p>

                <form method="POST" action="{{ route('register.store') }}" class="form" novalidate data-password-form>
                    @csrf
                    <input type="hidden" name="_auth_pane" value="register">

                    <div class="name-grid" role="group" aria-label="Your name">
                        <div class="field">
                            <label class="field__label" for="first_name">First name</label>
                            <input class="field__input" id="first_name" type="text" name="first_name"
                                   value="{{ old('first_name') }}" required autocomplete="given-name"
                                   @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
                            @error('first_name')
                                <p class="field__error" id="first_name-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label class="field__label" for="last_name">Last name</label>
                            <input class="field__input" id="last_name" type="text" name="last_name"
                                   value="{{ old('last_name') }}" required autocomplete="family-name"
                                   @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
                            @error('last_name')
                                <p class="field__error" id="last_name-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <details class="name-extra">
                        <summary class="name-extra__summary">
                            Add middle name or suffix
                            <span class="name-extra__tag">(optional)</span>
                        </summary>
                        <div class="name-grid name-grid--extra">
                            <div class="field">
                                <label class="field__label" for="middle_name">Middle name</label>
                                <input class="field__input" id="middle_name" type="text" name="middle_name"
                                       value="{{ old('middle_name') }}" autocomplete="additional-name"
                                       @error('middle_name') aria-invalid="true" aria-describedby="middle_name-error" @enderror>
                                @error('middle_name')
                                    <p class="field__error" id="middle_name-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="field__label" for="suffix">Suffix</label>
                                <select class="field__input" id="suffix" name="suffix" autocomplete="honorific-suffix"
                                        @error('suffix') aria-invalid="true" aria-describedby="suffix-error" @enderror>
                                    <option value="">None</option>
                                    @foreach (\App\Http\Requests\RegisterRequest::SUFFIXES as $suffixOption)
                                        <option value="{{ $suffixOption }}" @selected(old('suffix') === $suffixOption)>{{ $suffixOption }}</option>
                                    @endforeach
                                </select>
                                @error('suffix')
                                    <p class="field__error" id="suffix-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </details>

                    <div class="field">
                        <label class="field__label" for="email">Email address</label>
                        <div class="field__control">
                            <span class="field__icon">{!! $mailIcon !!}</span>
                            <input class="field__input" id="email" type="email" name="email" value="{{ old('email') }}"
                                   autocomplete="email"
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')
                            <p class="field__error" id="email-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="phone">Mobile number</label>
                        <div class="field__control">
                            <span class="field__icon">{!! $phoneIcon !!}</span>
                            <input class="field__input" id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                                   autocomplete="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                                   aria-describedby="phone-hint"
                                   @error('phone') aria-invalid="true" aria-describedby="phone-hint phone-error" @enderror>
                        </div>
                        <p class="field__hint" id="phone-hint">Provide an email, a mobile number, or both.</p>
                        @error('phone')
                            <p class="field__error" id="phone-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="register-password">Password</label>
                        <div class="field__password field__control">
                            <span class="field__icon">{!! $lockIcon !!}</span>
                            <input class="field__input" id="register-password" type="password" name="password"
                                   required minlength="8" autocomplete="new-password"
                                   aria-describedby="password-rules">
                        </div>
                        <ul class="password-rules" id="password-rules" aria-live="polite">
                            <li class="password-rule" data-rule="min"><span class="rule-icon" aria-hidden="true">&times;</span> At least 8 characters</li>
                            <li class="password-rule" data-rule="upper"><span class="rule-icon" aria-hidden="true">&times;</span> One uppercase letter</li>
                            <li class="password-rule" data-rule="lower"><span class="rule-icon" aria-hidden="true">&times;</span> One lowercase letter</li>
                            <li class="password-rule" data-rule="num"><span class="rule-icon" aria-hidden="true">&times;</span> One number</li>
                            <li class="password-rule" data-rule="sym"><span class="rule-icon" aria-hidden="true">&times;</span> One special character</li>
                        </ul>
                        @error('password')
                            <p class="field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="register-password_confirmation">Confirm password</label>
                        <div class="field__password field__control">
                            <span class="field__icon">{!! $lockIcon !!}</span>
                            <input class="field__input" id="register-password_confirmation" type="password"
                                   name="password_confirmation" required minlength="8" autocomplete="new-password"
                                   aria-describedby="password-match">
                        </div>
                        <p class="field__match" id="password-match" data-password-match aria-live="polite">Passwords match</p>
                    </div>

                    <p class="auth-card__agreed">
                        You've read and agreed to the
                        <a class="link" href="{{ route('terms') }}">Terms and Conditions</a> and
                        <a class="link" href="{{ route('privacy') }}">Privacy Policy</a>.
                    </p>

                    <button type="submit" class="btn btn--primary btn--block" data-password-submit
                            data-loading-label="Creating account...">Create account</button>
                    <p class="field__hint" data-submit-reason id="submit-reason" aria-live="polite"></p>
                </form>
            @endif
        </div>
    </section>

    {{-- ---------- Sliding overlay ---------- --}}
    <div class="auth-card__overlay" data-auth-overlay>
        <div class="auth-card__overlay-inner">
            <div class="auth-card__overlay-panel auth-card__overlay-panel--left"
                 data-auth-overlay-panel="register"
                 @if (! $isRegister) aria-hidden="true" @endif>
                <h2 class="auth-card__overlay-title">Welcome back!</h2>
                <p class="auth-card__overlay-text">Already have an account?</p>
                <a class="btn auth-card__switch" href="{{ route('login') }}" data-auth-switch="login">Sign in</a>
            </div>
            <div class="auth-card__overlay-panel auth-card__overlay-panel--right"
                 data-auth-overlay-panel="login"
                 @if ($isRegister) aria-hidden="true" @endif>
                <h2 class="auth-card__overlay-title">Hello, Welcome!</h2>
                <p class="auth-card__overlay-text">Don&rsquo;t have an account?</p>
                <a class="btn auth-card__switch" href="{{ route('register') }}" data-auth-switch="register">Register</a>
            </div>
        </div>
    </div>
</div>
