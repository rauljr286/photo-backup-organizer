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

                {{-- Phone-only keypad. Writes straight into #private_password above;
                     the real input stays in the DOM for autofill and form submit. --}}
                <div class="private-keypad" data-private-keypad role="group" aria-label="Numeric keypad">
                    <button type="button" class="private-keypad__key" data-key="1">1</button>
                    <button type="button" class="private-keypad__key" data-key="2">2</button>
                    <button type="button" class="private-keypad__key" data-key="3">3</button>
                    <button type="button" class="private-keypad__key" data-key="4">4</button>
                    <button type="button" class="private-keypad__key" data-key="5">5</button>
                    <button type="button" class="private-keypad__key" data-key="6">6</button>
                    <button type="button" class="private-keypad__key" data-key="7">7</button>
                    <button type="button" class="private-keypad__key" data-key="8">8</button>
                    <button type="button" class="private-keypad__key" data-key="9">9</button>
                    <span class="private-keypad__key private-keypad__key--spacer" aria-hidden="true"></span>
                    <button type="button" class="private-keypad__key" data-key="0">0</button>
                    <button type="button" class="private-keypad__key private-keypad__key--back" data-key="back" aria-label="Delete last digit">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="18" y1="9" x2="12" y2="15"></line><line x1="12" y1="9" x2="18" y2="15"></line></svg>
                    </button>
                </div>
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

                {{-- Phone-only keypad; writes into #private_password in the form
                     above without ever logging or storing the digits. --}}
                <div class="private-keypad" data-private-keypad role="group" aria-label="Numeric keypad">
                    <button type="button" class="private-keypad__key" data-key="1">1</button>
                    <button type="button" class="private-keypad__key" data-key="2">2</button>
                    <button type="button" class="private-keypad__key" data-key="3">3</button>
                    <button type="button" class="private-keypad__key" data-key="4">4</button>
                    <button type="button" class="private-keypad__key" data-key="5">5</button>
                    <button type="button" class="private-keypad__key" data-key="6">6</button>
                    <button type="button" class="private-keypad__key" data-key="7">7</button>
                    <button type="button" class="private-keypad__key" data-key="8">8</button>
                    <button type="button" class="private-keypad__key" data-key="9">9</button>
                    <span class="private-keypad__key private-keypad__key--spacer" aria-hidden="true"></span>
                    <button type="button" class="private-keypad__key" data-key="0">0</button>
                    <button type="button" class="private-keypad__key private-keypad__key--back" data-key="back" aria-label="Delete last digit">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="18" y1="9" x2="12" y2="15"></line><line x1="12" y1="9" x2="18" y2="15"></line></svg>
                    </button>
                </div>

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
            <x-upload-dropzone :url="route('private.photos.store')" :private="true"></x-upload-dropzone>

            <section class="photo-section private-gallery" id="photo-section" aria-labelledby="private-grid-heading">
                <h2 class="visually-hidden" id="private-grid-heading">Your private photos</h2>

            <div class="photo-grid" id="photo-grid">
                @foreach ($photos as $photo)
                    <x-photo-card :photo="$photo" :selectable="true"></x-photo-card>
                @endforeach
            </div>


                @if ($photos->hasMorePages())
                    <div class="grid-sentinel" data-infinite-scroll
                         data-page-url="{{ route('private.photos.page') }}"
                         data-next-page="{{ $photos->currentPage() + 1 }}"
                         data-has-more="true" aria-live="polite">
                        <span class="grid-sentinel__spinner" aria-hidden="true"></span>
                        <span class="grid-sentinel__text">Loading more photos&hellip;</span>
                    </div>
                @endif
            </section>
        @endif
    </div>

    <div class="bulk-bar" id="bulk-bar" role="region" aria-label="Bulk actions" hidden>
        <p><strong id="bulk-count">0</strong> selected</p>
        <div class="bulk-bar__actions">
            <button type="button" class="btn btn--primary btn--sm" id="download-selected" data-url="{{ route('photos.download-selected') }}">Download selected</button>
            <button type="button" class="btn btn--primary btn--sm" id="edit-selected">Edit details</button>
            <button type="button" class="btn btn--primary btn--sm" id="archive-selected">Archive selected</button>
            <button type="button" class="btn btn--ghost btn--sm" id="clear-selected">Clear selection</button>
        </div>
    </div>
</x-app-layout>
