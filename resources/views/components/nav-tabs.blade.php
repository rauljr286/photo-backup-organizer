@props(['compact' => false])

<nav class="tabnav @if ($compact) tabnav--compact @endif" aria-label="Primary">
    <a href="{{ route('dashboard') }}" @class(['tabnav__link', 'is-active' => request()->routeIs('dashboard')])>
        <svg class="tabnav__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <path d="m21 15-5-5L5 21"></path>
        </svg>
        <span>Photos</span>
    </a>

    <a href="{{ route('albums.index') }}" @class(['tabnav__link', 'is-active' => request()->routeIs('albums.*')])>
        <svg class="tabnav__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
        </svg>
        <span>Albums</span>
    </a>

    <a href="{{ route('trash') }}" @class(['tabnav__link', 'is-active' => request()->routeIs('trash')])>
        <svg class="tabnav__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M3 6h18"></path>
            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
            <path d="M10 11v6"></path>
            <path d="M14 11v6"></path>
        </svg>
        <span>Trash</span>
        @if (auth()->user()->photos()->onlyTrashed()->count() > 0)
            <span class="badge tabnav__badge">{{ auth()->user()->photos()->onlyTrashed()->count() }}</span>
        @endif
    </a>

</nav>