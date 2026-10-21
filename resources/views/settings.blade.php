<x-app-layout :title="'Settings'" body-class="profile-page">
    <div class="profile">
        <x-storage-warning :user="$user"></x-storage-warning>

        <x-hero-banner
            :greeting="'Your profile'"
            :eyebrow="'Account & preferences'"
            :stats="[
                ['icon' => 'photo', 'value' => $photoCount, 'label' => 'Photos'],
                ['icon' => 'album', 'value' => $albumCount, 'label' => 'Albums'],
                ['icon' => 'storage', 'value' => $usedPercent . '%', 'label' => 'Storage used', 'note' => number_format($usedBytes / 1024 / 1024, 1) . ' of ' . number_format($limitBytes / 1024 / 1024, 0) . ' MB'],
            ]"
        ></x-hero-banner>

        <div class="profile__head">
            <h2 class="profile__head-title">Account settings</h2>
        </div>

        <div class="profile__body">
            <nav class="profile__tabs" role="tablist" aria-label="Settings sections">
                <a role="tab" aria-selected="{{ $activeTab === 'profile' ? 'true' : 'false' }}"
                   class="profile__tab @if ($activeTab === 'profile') is-active @endif"
                   href="{{ route('profile') }}" id="tab-profile">
                    <svg class="profile__tab-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Profile</span>
                </a>
                <a role="tab" aria-selected="{{ $activeTab === 'security' ? 'true' : 'false' }}"
                   class="profile__tab @if ($activeTab === 'security') is-active @endif"
                   href="{{ route('profile.security') }}" id="tab-security">
                    <svg class="profile__tab-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Security</span>
                </a>
                <a role="tab" aria-selected="{{ $activeTab === 'privacy' ? 'true' : 'false' }}"
                   class="profile__tab @if ($activeTab === 'privacy') is-active @endif"
                   href="{{ route('profile.privacy') }}" id="tab-privacy">
                    <svg class="profile__tab-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10Z"></path>
                    </svg>
                    <span>Privacy &amp; data</span>
                </a>
                <a role="tab" aria-selected="{{ $activeTab === 'private' ? 'true' : 'false' }}"
                   class="profile__tab @if ($activeTab === 'private') is-active @endif"
                   href="{{ route('profile.private') }}" id="tab-private" data-private-gate>
                    <svg class="profile__tab-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Private</span>
                </a>
            </nav>

            <div class="profile__panels">
                @if ($activeTab === 'profile')
                    <section class="profile__panel profile__panel--profile" role="tabpanel" aria-labelledby="tab-profile">
                        <h2 class="profile__card-title">Profile picture</h2>

                        <div class="profile__picture">
                            <div class="profile__avatar-col">
                                <span class="profile__avatar">
                                    <x-avatar :user="$user" size="lg"></x-avatar>
                                </span>

                                <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="profile__avatar-form" id="avatar-form">
                                    @csrf
                                    <input class="visually-hidden" type="file" id="avatar-file" name="avatar"
                                           accept="image/jpeg,image/png,image/webp" data-avatar-input
                                           aria-label="Choose a profile picture">
                                    <label class="profile__upload" for="avatar-file" data-avatar-edit>
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                            <circle cx="12" cy="13" r="4"></circle>
                                        </svg>
                                        <span>Upload photo</span>
                                    </label>
                                </form>

                                <p class="profile__upload-error" data-avatar-error role="alert" hidden></p>
                            </div>
                        </div>

                        <dl class="profile__rows">
                            <div class="profile__row profile__row--name">
                                <dt>Name</dt>
                                <dd>
                                    <div class="name-edit" data-name-view>
                                        <span class="name-edit__current" data-name-display>{{ $user->name }}</span>
                                        <button type="button" class="profile__edit" data-name-trigger aria-label="Edit name">
                                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"></path>
                                            </svg>
                                            <span>Edit</span>
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
                            <div class="profile__row"><dt>Email</dt><dd>{{ $user->email ?: 'Not provided' }}</dd></div>
                            <div class="profile__row"><dt>Storage plan</dt><dd>{{ number_format($user->storage_limit_mb, 0) }} MB</dd></div>
                            <div class="profile__row"><dt>Photos</dt><dd>{{ $photoCount }} photo(s)</dd></div>
                            <div class="profile__row"><dt>Albums</dt><dd>{{ $albumCount }} album(s)</dd></div>
                            <div class="profile__row"><dt>Member since</dt><dd>{{ $user->created_at?->format('F j, Y') }}</dd></div>
                        </dl>

                        <p class="profile__note">
                            Archived photos stay safely stored until you choose to restore them. You can request full account deletion in the Security tab at any time.
                        </p>
                    </section>
                @endif

                @if ($activeTab === 'security')
                    <section class="profile__panel" role="tabpanel" aria-labelledby="tab-security">
                        <h2 class="profile__card-title">Change your password</h2>
                        <form method="POST" action="{{ route('settings.password') }}" class="form" data-password-form>
                            @csrf
                            @method('PATCH')

                            <div class="field">
                                <label class="field__label" for="current_password">Current password</label>
                                <input class="field__input" id="current_password" type="password" name="current_password" required autocomplete="current-password">
                            </div>
                            <div class="field">
                                <label class="field__label" for="password">New password</label>
                                <input class="field__input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="password-rules">
                            </div>
                            <ul class="password-rules" id="password-rules">
                                <li class="password-rule" data-rule="min"><span class="rule-icon">✕</span> At least 8 characters</li>
                                <li class="password-rule" data-rule="upper"><span class="rule-icon">✕</span> One uppercase letter</li>
                                <li class="password-rule" data-rule="lower"><span class="rule-icon">✕</span> One lowercase letter</li>
                                <li class="password-rule" data-rule="num"><span class="rule-icon">✕</span> One number</li>
                                <li class="password-rule" data-rule="sym"><span class="rule-icon">✕</span> One special character</li>
                            </ul>
                            <div class="field">
                                <label class="field__label" for="password_confirmation">Confirm new password</label>
                                <input class="field__input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                            </div>
                            <p class="field__match" data-password-match aria-live="polite"></p>
                            <p class="field__error" data-submit-reason aria-live="polite"></p>
                            <button type="submit" class="btn btn--primary" data-password-submit>Update password</button>
                        </form>
                    </section>
                @endif

                @if ($activeTab === 'privacy')
                    <section class="profile__panel profile__panel--privacy" role="tabpanel" aria-labelledby="tab-privacy">
                        <h2 class="profile__card-title">Privacy &amp; your data</h2>

                        <p class="profile__privacy-text">
                            Your photos are private to your account. Review the full
                            <a class="profile__link" href="{{ route('privacy') }}">Privacy Policy</a> and
                            <a class="profile__link" href="{{ route('terms') }}">Terms and Conditions</a>.
                        </p>

                        @if ($user->hasRequestedDeletion())
                            <div class="profile__danger">
                                <h3 class="profile__danger-title">Account deletion requested</h3>
                                <p class="profile__danger-text">
                                    You requested account deletion on
                                    {{ $user->deletion_requested_at->format('F j, Y g:i a') }}. Nothing has been deleted yet — it is awaiting manual review. If you changed your mind, you can cancel this request below.
                                </p>
                                <form method="POST" action="{{ route('profile.deletion-request.cancel') }}" class="profile__danger-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="profile__danger-cancel">Cancel deletion request</button>
                                </form>
                            </div>
                        @else
                            <div class="profile__danger">
                                <h3 class="profile__danger-title">Delete your account and all photos</h3>
                                <p class="profile__danger-text">
                                    This permanently deletes your account, all of your photos, albums and any pending uploads. This action cannot be undone.
                                </p>
                                <form method="POST" action="{{ route('profile.deletion-request') }}" class="profile__danger-form" data-deletion-request-form>
                                    @csrf
                                    <label class="profile__danger-check">
                                        <input type="checkbox" name="confirm" value="1" required>
                                        <span>I understand this is a request for manual review, not an immediate deletion.</span>
                                    </label>
                                    <button type="submit" class="profile__danger-btn">Request account deletion</button>
                                </form>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($activeTab === 'private')
                    <section class="profile__panel profile__panel--private" role="tabpanel" aria-labelledby="tab-private">
                        <h2 class="profile__card-title">Private gallery</h2>

                        <p class="profile__privacy-text">
                            The private gallery is a separate, password-protected space for photos you want to keep out of your
                            main library. Private photos never appear in Photos, Albums, Insights, Archive or your storage
                            totals &mdash; they are only visible in the private gallery, after you enter this password.
                        </p>

                        <div class="private-status">
                            <span class="private-status__badge @if ($privateConfigured) is-on @else is-off @endif">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <span>{{ $privateConfigured ? 'Private password set' : 'Not set up yet' }}</span>
                            </span>
                            @if ($privateConfigured)
                                <span class="private-status__meta">
                                    {{ number_format($privatePhotoCount) }} private {{ \Illuminate\Support\Str::plural('photo', $privatePhotoCount) }}
                                    &middot; {{ $privateUnlocked ? 'unlocked for this session' : 'currently locked' }}
                                </span>
                            @endif
                        </div>

                        @if (! $privateConfigured)
                            <form method="POST" action="{{ route('private.password') }}" class="form">
                                @csrf

                                <div class="field">
                                    <label class="field__label" for="private_password">Create a private password</label>
                                    <input class="field__input" id="private_password" type="password" name="private_password"
                                           required minlength="4" autocomplete="new-password" aria-describedby="private-pw-hint">
                                    <p class="field__hint" id="private-pw-hint">At least 4 characters. This is separate from your account password.</p>
                                </div>
                                <div class="field">
                                    <label class="field__label" for="private_password_confirmation">Confirm private password</label>
                                    <input class="field__input" id="private_password_confirmation" type="password" name="private_password_confirmation"
                                           required minlength="4" autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn--primary">Set private password</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('private.password') }}" class="form">
                                @csrf
                                <h3 class="profile__card-title">Change private password</h3>

                                <div class="field">
                                    <label class="field__label" for="current_private_password">Current private password</label>
                                    <input class="field__input" id="current_private_password" type="password" name="current_private_password"
                                           required autocomplete="current-password">
                                </div>
                                <div class="field">
                                    <label class="field__label" for="private_password">New private password</label>
                                    <input class="field__input" id="private_password" type="password" name="private_password"
                                           required minlength="4" autocomplete="new-password">
                                </div>
                                <div class="field">
                                    <label class="field__label" for="private_password_confirmation">Confirm new private password</label>
                                    <input class="field__input" id="private_password_confirmation" type="password" name="private_password_confirmation"
                                           required minlength="4" autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn--primary">Change private password</button>
                            </form>

                            <div class="private-block">
                                <h3 class="private-block__title">Forgot your private password?</h3>
                                <p class="private-block__text">
                                    Reset it using your account password. This locks the private gallery, and you unlock it again with the new password.
                                </p>

                                <form method="POST" action="{{ route('private.reset') }}" class="form">
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
                            </div>
                        @endif

                        <p class="profile__note">
                            <a class="profile__link" href="{{ route('private') }}" data-private-gate>Open the private gallery</a> to upload and view your private photos.
                        </p>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
