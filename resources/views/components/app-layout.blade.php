<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                <div class="topbar__left">
                    <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false">
                        <svg id="sidebar-toggle-icon-open" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                        <svg id="sidebar-toggle-icon-close" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" hidden>
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>

                    <a href="{{ route('dashboard') }}" class="brand topbar__brand" aria-label="Photo Backup Organizer home">
                        <svg class="brand__icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path vector-effect="non-scaling-stroke" transform="translate(12 5) scale(0.5) translate(-12 -11)" d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
                            <path d="M5 11.5h3.6l1.2-2h4.4l1.2 2H19a2 2 0 0 1 2 2v4.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13.5a2 2 0 0 1 2-2Z"/>
                            <circle cx="12" cy="15.5" r="2.5"/>
                        </svg>
                        <span>Photo Backup</span>
                    </a>
                </div>

                <div class="topbar__nav">
                    <x-nav-tabs></x-nav-tabs>
                </div>

                <div class="topbar__account">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm btn--icon topbar__logout" aria-label="Sign out" title="Sign out">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </button>
                    </form>

                    <a class="topbar__user" href="{{ route('settings') }}" aria-label="View your profile and personal details">
                        <x-avatar :user="auth()->user()" size="sm"></x-avatar>
                        <span class="topbar__name">{{ auth()->user()->name }}</span>
                    </a>
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
                <a class="sidebar__user" href="{{ route('settings') }}">
                    <x-avatar :user="auth()->user()" size="sm"></x-avatar>
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

    <dialog class="dialog" id="bulk-delete-dialog" aria-labelledby="bulk-delete-title" aria-describedby="bulk-delete-desc">
        <h2 class="dialog__title" id="bulk-delete-title">Move selected photos to trash?</h2>
        <p id="bulk-delete-desc">
            <strong id="bulk-delete-name"></strong> photo(s) will move to the trash where you can still
            restore them for {{ config('photobackup.trash_retention_days', 30) }} days.
        </p>
        <form method="POST" id="bulk-delete-form" class="dialog__actions">
            @csrf
            <button type="button" class="btn btn--ghost js-dialog-close" data-target="bulk-delete-dialog">Cancel</button>
            <button type="submit" class="btn btn--danger">Move to trash</button>
        </form>
    </dialog>
</body>
</html>