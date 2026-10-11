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

    <a href="{{ route('archive') }}" @class(['tabnav__link', 'is-active' => request()->routeIs('archive')])>
        <svg class="tabnav__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="9" x2="21" y2="9"></line>
            <path d="M9 14h6"></path>
        </svg>
        <span>Archive</span>
        @if (auth()->user()->photos()->archived()->count() > 0)
            <span class="badge tabnav__badge">{{ auth()->user()->photos()->archived()->count() }}</span>
        @endif
    </a>

    <a href="{{ route('insights') }}" @class(['tabnav__link', 'is-active' => request()->routeIs('insights')])>
        <svg class="tabnav__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <line x1="18" y1="20" x2="18" y2="10"></line>
            <line x1="12" y1="20" x2="12" y2="4"></line>
            <line x1="6" y1="20" x2="6" y2="14"></line>
        </svg>
        <span>Insights</span>
    </a>

    <a href="{{ route('cleanup') }}" @class(['tabnav__link', 'is-active' => request()->routeIs('cleanup')])>
        <svg class="tabnav__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="m12 3-1.9 5.8a2 2 0 0 1-1.287 1.288L3 12l5.8 1.9a2 2 0 0 1 1.288 1.287L12 21l1.9-5.8a2 2 0 0 1 1.287-1.288L21 12l-5.8-1.9a2 2 0 0 1-1.288-1.287Z"></path>
        </svg>
        <span>Cleanup</span>
    </a>

</nav>