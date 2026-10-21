<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <x-theme-head />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Back up, organize and browse your photos securely and privately.">
    <title>{{ $title ?? 'Photo Backup Organizer' }} - Photo Backup</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass ?? '' }}">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <div class="app-shell">
        <header class="topbar">
            <div class="topbar__inner">
                <a href="{{ route('dashboard') }}" class="topbar__brand brand" aria-label="Photo Backup Organizer home">
                    <svg class="brand__icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path vector-effect="non-scaling-stroke" transform="translate(12 5) scale(0.5) translate(-12 -11)" d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
                        <path d="M5 11.5h3.6l1.2-2h4.4l1.2 2H19a2 2 0 0 1 2 2v4.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13.5a2 2 0 0 1 2-2Z"/>
                        <circle cx="12" cy="15.5" r="2.5"/>
                    </svg>
                    <span>Photo Backup Organizer</span>
                </a>

                <div class="topbar__nav">
                    <x-nav-tabs></x-nav-tabs>
                </div>

                @php($account = config('navigation.account'))
                <div class="topbar__account">
                    <div class="user-menu" data-user-menu>
                        <button type="button" class="topbar__user user-menu__trigger" data-user-menu-trigger
                                aria-haspopup="true" aria-expanded="false" aria-controls="user-menu-panel" aria-label="Account menu">
                            <span class="avatar avatar--sm" role="img" aria-label="Signed in as {{ auth()->user()->name }}">
                                <span class="avatar__initials" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                            </span>
                            <svg class="user-menu__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div class="user-menu__panel" id="user-menu-panel" data-user-menu-panel role="menu" aria-label="Account" hidden>
                            <p class="user-menu__header">
                                <span class="user-menu__name">{{ auth()->user()->name }}</span>
                                @if (auth()->user()->email)
                                    <span class="user-menu__email">{{ auth()->user()->email }}</span>
                                @endif
                            </p>

                            <a class="user-menu__item" href="{{ route($account['profile']['route']) }}" role="menuitem">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $account['profile']['icon'] !!}</svg>
                                <span>{{ $account['profile']['label'] }}</span>
                            </a>

                            <x-theme-toggle class="user-menu__item" label="Dark mode" role="menuitem" />

                            <div class="user-menu__divider" role="separator"></div>

                            <x-sign-out />
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="topbar__mobile">
            @if (!empty($backUrl ?? null))
                <a class="topbar__mobile-back" href="{{ $backUrl }}" aria-label="Back">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </a>
            @endif
            <span class="topbar__mobile-title">{{ $title ?? '' }}</span>
        </div>

        <div class="app-main">
            <div class="offline-banner" data-offline-banner role="status" aria-live="polite" hidden>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 1l22 22"></path><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path><path d="M10.71 5.05A16 16 0 0 1 22.58 9"></path><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                <span>You&rsquo;re offline &mdash; showing saved content.</span>
            </div>

            <main id="main-content" class="page">
                @if (session('status'))
                    <div class="flash flash--success" role="status">
                        <p>{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="flash flash--error" role="alert">
                        <p class="flash__title">Something needs your attention:</p>
                        <ul class="flash__list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        <x-mobile-nav />
    </div>

    <x-flash-region></x-flash-region>

    @unless (request()->routeIs('private'))
        <x-private-gate />
    @endunless

    <dialog class="dialog" id="bulk-archive-dialog" aria-labelledby="bulk-archive-title" aria-describedby="bulk-archive-desc">
        <h2 class="dialog__title" id="bulk-archive-title">Archive selected photos?</h2>
        <p id="bulk-archive-desc">
            <strong id="bulk-archive-name"></strong> photo(s) will move to your archive where you can
            restore them at any time.
        </p>
        <form method="POST" id="bulk-archive-form" class="dialog__actions">
            @csrf
            <button type="button" class="btn btn--ghost js-dialog-close" data-target="bulk-archive-dialog">Cancel</button>
            <button type="submit" class="btn btn--danger">Archive</button>
        </form>
    </dialog>

    <dialog class="dialog" id="bulk-edit-dialog" aria-labelledby="bulk-edit-title" aria-describedby="bulk-edit-desc">
        <h2 class="dialog__title" id="bulk-edit-title">Edit selected photos</h2>
        <p id="bulk-edit-desc">Updating <strong id="bulk-edit-name"></strong> photo(s) - the details below replace the
            current description and tags on every selected photo.</p>

        <form method="POST" id="bulk-edit-form" class="form" data-url="{{ route('photos.bulk-update') }}">
            @csrf

            <div class="field">
                <label class="field__label" for="bulk-alt-text">Description (alt text)</label>
                <textarea class="field__input" id="bulk-alt-text" name="alt_text" rows="3"
                          placeholder="Describe what is happening in these photos"></textarea>
            </div>

            <div class="field">
                <label class="field__label" for="bulk-tags">Tags (replaces the tags on all selected)</label>
                <input class="field__input" id="bulk-tags" name="tags" type="text"
                       placeholder="e.g. family, vacation, 2025">
                <p class="field__hint">Separate tags with commas.</p>
            </div>

            <div class="field">
                <label class="field__label" for="bulk-album">Add to album</label>
                <select class="field__input" id="bulk-album" name="album">
                    <option value="">No album change</option>
                    @foreach (auth()->user()->albums()->orderBy('name')->get() as $album)
                        <option value="{{ $album->id }}">{{ $album->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="dialog__actions">
                <button type="button" class="btn btn--ghost js-dialog-close" data-target="bulk-edit-dialog">Cancel</button>
                <button type="submit" class="btn btn--primary">Save changes</button>
            </div>
        </form>
    </dialog>
</body>
</html>