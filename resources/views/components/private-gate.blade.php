@php
    $user = auth()->user();
    $locked = $user
        && $user->hasPrivatePassword()
        && ! \App\Support\PrivateGallery::isUnlocked(request());
@endphp

@if ($locked)
    <dialog class="dialog private-gate" id="private-gate-dialog" aria-labelledby="private-gate-title" aria-describedby="private-gate-desc">
        <h2 class="dialog__title" id="private-gate-title">Private gallery is locked</h2>
        <p id="private-gate-desc">Enter your private password to view and upload private photos.</p>

        <form method="POST" action="{{ route('private.unlock') }}" class="form">
            @csrf

            <div class="field">
                <label class="field__label" for="private_gate_password">Private password</label>
                <input class="field__input" id="private_gate_password" type="password" name="private_password"
                       required autocomplete="current-password" data-private-gate-focus>
            </div>

            <div class="dialog__actions">
                <button type="button" class="btn btn--ghost js-dialog-close" data-target="private-gate-dialog">Cancel</button>
                <button type="submit" class="btn btn--primary">Unlock</button>
            </div>
        </form>

        <details class="private-lock__reset">
            <summary>Forgot your private password?</summary>
            <p class="private-lock__text">Reset it with your account password. You will then unlock the gallery with the new password.</p>

            <form method="POST" action="{{ route('private.reset') }}" class="form">
                @csrf

                <div class="field">
                    <label class="field__label" for="private_gate_reset_current_password">Account password</label>
                    <input class="field__input" id="private_gate_reset_current_password" type="password" name="current_password"
                           required autocomplete="current-password">
                </div>
                <div class="field">
                    <label class="field__label" for="private_gate_reset_private_password">New private password</label>
                    <input class="field__input" id="private_gate_reset_private_password" type="password" name="private_password"
                           required minlength="4" autocomplete="new-password">
                </div>
                <div class="field">
                    <label class="field__label" for="private_gate_reset_private_password_confirmation">Confirm new private password</label>
                    <input class="field__input" id="private_gate_reset_private_password_confirmation" type="password" name="private_password_confirmation"
                           required minlength="4" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn--secondary">Reset private password</button>
            </form>
        </details>
    </dialog>
@endif
