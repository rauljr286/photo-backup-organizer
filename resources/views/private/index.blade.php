<x-app-layout :title="'Private'" body-class="private-page">
    <div class="dashboard">
        <div class="section-head">
            <span class="section-head__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                    <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </span>
            <div class="section-head__text">
                <h1 class="section-head__title">Private gallery</h1>
                <p class="section-head__subtitle">
                    @if ($mode === 'gallery')
                        {{ number_format($photos->total()) }} {{ \Illuminate\Support\Str::plural('photo', $photos->total()) }} &middot; kept out of your main library.
                    @else
                        A separate, password-protected space for your most private photos.
                    @endif
                </p>
            </div>
            @if ($mode === 'gallery')
                <div class="section-head__actions">
                    <form method="POST" action="{{ route('private.lock') }}" class="inline-form">
                        @csrf
                        <button type="submit" class="btn btn--primary">Lock gallery</button>
                    </form>
                </div>
            @endif
        </div>

        @if ($mode === 'setup')
            <div class="private-lock">
                <span class="private-lock__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </span>
                <h2 class="private-lock__title">Set up your private gallery</h2>
                <p class="private-lock__text">
                    Choose a password for the private gallery. It is stored separately from your account password, and you will
                    enter it whenever you want to unlock this space. Private photos never appear in your main library.
                </p>

                <form method="POST" action="{{ route('private.password') }}" class="form private-lock__form">
                    @csrf

                    <div class="field">
                        <label class="field__label" for="private_password">Private password</label>
                        <input class="field__input" id="private_password" type="password" name="private_password"
                               required minlength="4" autocomplete="new-password" aria-describedby="private-setup-hint">
                        <p class="field__hint" id="private-setup-hint">At least 4 characters.</p>
                    </div>
                    <div class="field">
                        <label class="field__label" for="private_password_confirmation">Confirm private password</label>
                        <input class="field__input" id="private_password_confirmation" type="password" name="private_password_confirmation"
                               required minlength="4" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn--primary">Create private gallery</button>
                </form>
            </div>
        @elseif ($mode === 'locked')
            <div class="private-lock">
                <span class="private-lock__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </span>
                <h2 class="private-lock__title">Private gallery is locked</h2>
                <p class="private-lock__text">Enter your private password to view and upload private photos.</p>

                <form method="POST" action="{{ route('private.unlock') }}" class="form private-lock__form">
                    @csrf

                    <div class="field">
                        <label class="field__label" for="private_password">Private password</label>
                        <input class="field__input" id="private_password" type="password" name="private_password"
                               required autocomplete="current-password" autofocus>
                    </div>

                    <button type="submit" class="btn btn--primary">Unlock</button>
                </form>

                <details class="private-lock__reset">
                    <summary>Forgot your private password?</summary>
                    <p class="private-lock__text">Reset it with your account password. You will then unlock the gallery with the new password.</p>

                    <form method="POST" action="{{ route('private.reset') }}" class="form private-lock__form">
                        @csrf

                        <div class="field">
                            <label class="field__label" for="reset_current_password">Account password</label>
                            <input class="field__input" id="reset_current_password" type="password" name="current_password"
                                   required autocomplete="current-password">
                        </div>
                        <div class="field">
                            <label class="field__label" for="reset_private_password">New private password</label>
                            <input class="field__input" id="reset_private_password" type="password" name="private_password"
                                   required minlength="4" autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label class="field__label" for="reset_private_password_confirmation">Confirm new private password</label>
                            <input class="field__input" id="reset_private_password_confirmation" type="password" name="private_password_confirmation"
                                   required minlength="4" autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn--secondary">Reset private password</button>
                    </form>
                </details>
            </div>
        @else
            <x-upload-dropzone :url="route('private.photos.store')"></x-upload-dropzone>

            <section class="photo-section private-gallery" id="photo-section" aria-labelledby="private-grid-heading">
                <h2 class="visually-hidden" id="private-grid-heading">Your private photos</h2>

                <div class="photo-grid" id="photo-grid">
                    @foreach ($photos as $photo)
                        <x-photo-card :photo="$photo"></x-photo-card>
                    @endforeach
                </div>

                @if ($photos->hasPages())
                    <nav class="pagination" aria-label="Private photo pages">
                        {{ $photos->withQueryString()->links() }}
                    </nav>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
