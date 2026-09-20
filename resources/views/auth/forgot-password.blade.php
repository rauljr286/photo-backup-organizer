<x-guest-layout title="Reset your password">
    <h1 class="auth-shell__heading">Forgot your password?</h1>
    <p class="auth-shell__lead">
        Enter your email address and we&rsquo;ll send you a link to set a new password.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="form auth-shell__form" novalidate>
        @csrf

        <div class="field">
            <label class="field__label" for="email">Email address</label>
            <input class="field__input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Send reset link</button>
    </form>

    <p class="auth-shell__alt">
        Remembered it?
        <a class="link" href="{{ route('login') }}">Back to sign in</a>
    </p>
</x-guest-layout>