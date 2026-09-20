<x-guest-layout title="Set a new password">
    <h1 class="auth-shell__heading">Set a new password</h1>

    <form method="POST" action="{{ route('password.update') }}" class="form auth-shell__form" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label class="field__label" for="email">Email address</label>
            <input class="field__input" id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}" required autocomplete="email" readonly>
        </div>

        <div class="field">
            <label class="field__label" for="password">New password</label>
            <input class="field__input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
            <p class="field__hint">At least 8 characters.</p>
        </div>

        <div class="field">
            <label class="field__label" for="password_confirmation">Confirm new password</label>
            <input class="field__input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn--primary btn--block">Update password</button>
    </form>
</x-guest-layout>