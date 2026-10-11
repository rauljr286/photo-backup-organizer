<x-app-layout :title="'Settings'">
    <div class="settings">
        <x-storage-warning :user="auth()->user()"></x-storage-warning>

        <x-hero-banner
            :greeting="'Your profile'"
            :eyebrow="'Account & preferences'"
            :stats="[
                ['icon' => 'photo', 'value' => $photoCount, 'label' => 'Photos', 'tint' => 'stat__icon--primary'],
                ['icon' => 'album', 'value' => $albumCount, 'label' => 'Albums', 'tint' => 'stat__icon--accent'],
                ['icon' => 'storage', 'value' => $usedPercent . '%', 'label' => 'Storage used', 'note' => number_format($usedBytes / 1024 / 1024, 1) . ' of ' . number_format($limitBytes / 1024 / 1024, 0) . ' MB', 'meter' => $usedPercent, 'tint' => 'stat__icon--teal'],
            ]"
        ></x-hero-banner>

        <div class="section-head">
            <span class="section-head__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path>
                </svg>
            </span>
            <div class="section-head__text">
                <h2 class="section-head__title">Account settings</h2>
                <p class="section-head__subtitle">Manage your profile, password and privacy in one place.</p>
            </div>
        </div>

        <div class="settings__body">
            <nav class="settings__tabs" role="tablist" aria-label="Settings sections">
                <a role="tab" aria-selected="{{ $activeTab === 'profile' ? 'true' : 'false' }}" class="settings__tab @if ($activeTab === 'profile') is-active @endif"
                   href="{{ route('settings', ['tab' => 'profile']) }}" id="tab-profile">
                    <svg class="settings__tab-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Profile</span>
                </a>
                <a role="tab" aria-selected="{{ $activeTab === 'security' ? 'true' : 'false' }}" class="settings__tab @if ($activeTab === 'security') is-active @endif"
                   href="{{ route('settings', ['tab' => 'security']) }}" id="tab-security">
                    <svg class="settings__tab-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Security</span>
                </a>
                <a role="tab" aria-selected="{{ $activeTab === 'privacy' ? 'true' : 'false' }}" class="settings__tab @if ($activeTab === 'privacy') is-active @endif"
                   href="{{ route('settings', ['tab' => 'privacy']) }}" id="tab-privacy">
                    <svg class="settings__tab-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10Z"></path>
                    </svg>
                    <span>Privacy &amp; data</span>
                </a>
            </nav>

            @if ($activeTab === 'profile')
                <section class="panel settings__panel" role="tabpanel" aria-labelledby="tab-profile">
                    <h2 class="panel__heading settings__heading">
                        <span class="settings__heading-icon settings__heading-icon--primary" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                        </span>
                        Profile picture
                    </h2>

                    <div class="profile-card">
                        <div class="profile-card__avatar-wrap">
                            <span class="profile-card__avatar">
                                <x-avatar :user="$user" size="lg"></x-avatar>
                            </span>

                            <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="profile-card__avatar-form" id="avatar-form">
                                @csrf
                                <label class="avatar-edit" for="avatar-file" data-avatar-edit aria-label="Change your profile picture">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                    <span class="visually-hidden">Select a new profile picture</span>
                                </label>
                                <input class="visually-hidden" type="file" id="avatar-file" name="avatar"
                                       accept="image/jpeg,image/png,image/webp" data-avatar-input
                                       aria-label="Choose a profile picture">
                            </form>
                        </div>

                        <div class="profile-card__content">
                            <p class="profile-card__hint">
                                Click the camera icon to upload a JPG, PNG or WebP image (max 5MB). It will be
                                automatically cropped to a square.
                            </p>

                            <dl class="detail-list profile-card__details">
                                <div class="detail-list__row detail-list__row--name">
                                    <dt>Name</dt>
                                    <dd class="detail-list__value">
                                        <div class="name-edit" data-name-view>
                                            <span class="name-edit__current" data-name-display>{{ $user->name }}</span>
                                            <button type="button" class="btn btn--ghost btn--sm name-edit__trigger" data-name-trigger aria-label="Edit name">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <form class="name-edit name-edit__form" action="{{ route('settings.name') }}" method="POST" data-name-form hidden novalidate>
                                            @csrf
                                            @method('PATCH')
                                            <label class="visually-hidden" for="profile-name-input">Name</label>
                                            <input class="field__input name-edit__input" id="profile-name-input" type="text" name="name" value="{{ $user->name }}" maxlength="255" autocomplete="name" data-name-input required>
                                            <p class="name-edit__error" id="profile-name-error" data-name-error role="alert" hidden></p>
                                            <div class="name-edit__actions">
                                                <button type="submit" class="btn btn--primary btn--sm" data-name-save>Save</button>
                                                <button type="button" class="btn btn--ghost btn--sm" data-name-cancel>Cancel</button>
                                            </div>
                                        </form>
                                    </dd>
                                </div>
                                <div class="detail-list__row"><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                                <div class="detail-list__row"><dt>Storage plan</dt><dd>{{ number_format($user->storage_limit_mb, 0) }} MB</dd></div>
                                <div class="detail-list__row"><dt>Photos</dt><dd>{{ $photoCount }} photo(s)</dd></div>
                                <div class="detail-list__row"><dt>Albums</dt><dd>{{ $albumCount }} album(s)</dd></div>
                                <div class="detail-list__row"><dt>Member since</dt><dd>{{ $user->created_at?->format('F j, Y') }}</dd></div>
                            </dl>

                            <p class="settings__note">
                                Archived photos stay safely stored until you choose to restore them. You can request
                                full account deletion in the Security tab at any time.
                            </p>
                        </div>
                    </div>
                </section>
            @endif

            @if ($activeTab === 'security')
                <section class="panel settings__panel" role="tabpanel" aria-labelledby="tab-security">
                    <h2 class="panel__heading settings__heading">
                        <span class="settings__heading-icon settings__heading-icon--accent" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        Change your password
                    </h2>
                    <form method="POST" action="{{ route('settings.password') }}" class="form">
                        @csrf
                        @method('PATCH')

                        <div class="field">
                            <label class="field__label" for="current_password">Current password</label>
                            <input class="field__input" id="current_password" type="password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="field">
                            <label class="field__label" for="password">New password</label>
                            <input class="field__input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label class="field__label" for="password_confirmation">Confirm new password</label>
                            <input class="field__input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn--primary">Update password</button>
                    </form>
                </section>
            @endif

            @if ($activeTab === 'privacy')
                <section class="panel settings__panel" role="tabpanel" aria-labelledby="tab-privacy">
                    <h2 class="panel__heading settings__heading">
                        <span class="settings__heading-icon settings__heading-icon--primary" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <path d="M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10Z"></path>
                            </svg>
                        </span>
                        Privacy &amp; your data
                    </h2>

                    <p>
                        Your photos are private to your account. Review the full
                        <a class="link" href="{{ route('privacy') }}">Privacy Policy</a> and
                        <a class="link" href="{{ route('terms') }}">Terms and Conditions</a>.
                    </p>

                    <div class="settings__danger">
                        <h3 class="panel__subheading">Delete your account and all photos</h3>
                        <p>
                            This permanently deletes your account, all of your photos, albums and any pending
                            backups. Type your email address to confirm. There is no undo.
                        </p>
                        <form method="POST" action="{{ route('settings.delete-account') }}" class="form" id="delete-account-form">
                            @csrf
                            @method('DELETE')

                            <div class="field">
                                <label class="visually-hidden" for="delete-email">Type your email address to confirm deletion</label>
                                <input class="field__input" id="delete-email" type="email" name="email" autocomplete="off" required
                                       placeholder="Type {{ $user->email }} to confirm">
                            </div>
                            <button type="submit" class="btn btn--danger">Delete my account permanently</button>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>