<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-head />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Back up, organize and browse your photos securely and privately.">
    <title>{{ $title ?? 'Photo Backup Organizer' }} - Photo Backup</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <div class="app-shell">
        <header class="topbar">
            <div class="topbar__inner">
                <div class="topbar__nav">
                    <x-nav-tabs></x-nav-tabs>
                </div>

                <div class="topbar__account">
                    <div class="user-menu" data-user-menu>
                        <button type="button" class="topbar__user user-menu__trigger" data-user-menu-trigger
                                aria-haspopup="true" aria-expanded="false" aria-controls="user-menu-panel">
                            <span class="avatar avatar--sm" role="img" aria-label="Signed in as {{ auth()->user()->name }}">
                                <span class="avatar__initials" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                            </span>
                            <svg class="user-menu__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div class="user-menu__panel" id="user-menu-panel" data-user-menu-panel hidden>
                            <p class="user-menu__header">
                                <span class="user-menu__name">{{ auth()->user()->name }}</span>
                                @if (auth()->user()->email)
                                    <span class="user-menu__email">{{ auth()->user()->email }}</span>
                                @endif
                            </p>

                            <a class="user-menu__item" href="{{ route('settings') }}">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span>Profile &amp; settings</span>
                            </a>

                            <x-theme-toggle class="user-menu__item" label="Light mode" />

                            <form method="POST" action="{{ route('logout') }}" class="user-menu__logout">
                                @csrf
                                <button type="submit" class="user-menu__item user-menu__item--button">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    <span>Sign out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <aside class="sidebar" id="sidebar" aria-label="Primary navigation">
            <div class="sidebar__brand">
                <a href="{{ route('dashboard') }}" class="brand" aria-label="Photo Backup Organizer home">
                    <svg class="brand__icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2ZM8.5 13.5l2.5 3 3.5-4.5 4.5 6H5l3.5-4.5Z"/>
                    </svg>
                    <span>Photo Backup</span>
                </a>
            </div>

            <div class="sidebar__nav">
                <x-nav-tabs :compact="true"></x-nav-tabs>
            </div>

            <div class="sidebar__footer">
                <x-theme-toggle variant="sidebar" label="Light mode" />
                <a class="sidebar__user" href="{{ route('settings') }}">
                    <span class="avatar avatar--sm" role="img" aria-label="Signed in as {{ auth()->user()->name }}">
                        <span class="avatar__initials" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                    </span>
                    <span class="sidebar__name">{{ auth()->user()->name }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Sign out</button>
                </form>
            </div>
        </aside>

        <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>

        <div class="app-main">
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
    </div>

    <x-flash-region></x-flash-region>

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