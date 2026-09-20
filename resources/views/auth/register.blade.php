<x-guest-layout title="Create an account">
    @if (! $agreed)
        <section class="auth-legal" data-terms-step>
            <h1 class="auth-shell__heading" id="register-agreement-title">Before you create your account</h1>
            <p class="auth-shell__lead">
                Please read the full Terms and Conditions and Privacy Policy below. You must scroll
                to the bottom of the text before you can continue.
            </p>

            <div class="auth-legal__card">
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
                        @include('legal.partials.privacy-content', [
                            'retentionDays' => $retentionDays,
                            'supportEmail' => $supportEmail,
                        ])
                    </section>
                </div>

                <p class="auth-legal__hint" data-terms-hint>
                    Please scroll to the bottom of the text to continue.
                </p>

                <form method="POST" action="{{ route('register.agree') }}" class="auth-legal__actions">
                    @csrf
                    <button type="submit" class="btn btn--primary btn--block" data-terms-agree>I Agree</button>
                </form>
            </div>

            <p class="auth-shell__alt">
                Already have an account?
                <a class="link" href="{{ route('login') }}">Sign in</a>
            </p>
        </section>
    @else
        <h1 class="auth-shell__heading">Create your account</h1>
        <p class="auth-shell__lead">Your photos are backed up privately. Only you can see them.</p>

        <p class="auth-shell__agreed">
            You've read and agreed to the
            <a class="link" href="{{ route('terms') }}">Terms and Conditions</a> and
            <a class="link" href="{{ route('privacy') }}">Privacy Policy</a>.
        </p>

        <form method="POST" action="{{ route('register.store') }}" class="form auth-shell__form" novalidate>
            @csrf

            <div class="field">
                <label class="field__label" for="name">Your name</label>
                <input class="field__input" id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
            </div>

            <div class="field">
                <label class="field__label" for="email">Email address</label>
                <input class="field__input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            </div>

            <div class="field">
                <label class="field__label" for="password">Password</label>
                <input class="field__input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <p class="field__hint">At least 8 characters. Choose something unique.</p>
            </div>

            <div class="field">
                <label class="field__label" for="password_confirmation">Confirm password</label>
                <input class="field__input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn--primary btn--block">Create account</button>
        </form>

        <p class="auth-shell__alt">
            Already have an account?
            <a class="link" href="{{ route('login') }}">Sign in</a>
        </p>
    @endif
</x-guest-layout>