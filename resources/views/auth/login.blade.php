<x-guest-layout title="Sign in">
    <h1 class="auth-shell__heading">Sign in to your account</h1>

    <form method="POST" action="{{ route('login.attempt') }}" class="form auth-shell__form" novalidate>
        @csrf

        <div class="field">
            <label class="field__label" for="email">Email address</label>
            <input class="field__input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        </div>

        <div class="field">
            <label class="field__label" for="password">Password</label>
            <input class="field__input" id="password" type="password" name="password" required autocomplete="current-password">
        </div>

        <div class="field field--row">
            <label class="field__check">
                <input class="field__checkbox" type="checkbox" name="remember" value="1">
                <span>Keep me signed in</span>
            </label>
            <a class="link" href="{{ route('password.request') }}">Forgot your password?</a>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Sign in</button>
    </form>

    <p class="auth-shell__alt">
        Don&rsquo;t have an account?
        <a class="link" href="{{ route('register') }}">Create one now</a>
    </p>
</x-guest-layout>