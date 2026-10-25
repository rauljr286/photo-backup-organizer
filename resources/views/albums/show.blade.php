<x-app-layout :title="$album->name" :back-url="route('albums.index')">
    <div class="dashboard dashboard--stack">
        <div class="section-head">
            <span class="section-head__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
            </span>
            <div class="section-head__text">
                <a class="link section-head__back" href="{{ route('albums.index') }}">
                    <span class="section-head__back-icon" aria-hidden="true">&larr;</span>
                    All albums
                </a>
                <h1 class="section-head__title">{{ $album->name }}</h1>
                <p class="section-head__subtitle">{{ $photos->total() }} photo(s) in this album &middot; created {{ $album->created_at?->format('M j, Y') }}</p>
            </div>
            <div class="section-head__actions dashboard__top-actions">
                <a class="btn btn--primary" href="{{ route('albums.download', $album) }}">Download ZIP</a>
                <a class="btn btn--secondary" href="{{ route('dashboard', ['album' => $album->id]) }}">Open in photo grid</a>
            </div>
        </div>



        <button type="button" class="filter-sheet-trigger" data-filter-open aria-controls="filter-sheet">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
            <span>Filters</span>
        </button>

        <section class="filter-bar" id="filter-sheet" aria-labelledby="filter-heading">
            <span class="filter-sheet__handle" aria-hidden="true"></span>
            <h2 class="visually-hidden" id="filter-heading">Filter photos in this album</h2>
            <button type="button" class="filter-bar__close" data-filter-close aria-label="Close filters">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <form method="GET" action="{{ route('albums.show', $album) }}" class="filter-form" id="filter-form">
                <input type="hidden" name="album" value="{{ $album->id }}">

                <div class="field field--inline">
                    <label class="visually-hidden" for="search">Search photos</label>
                    <input class="field__input" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Search in album&hellip;">
                </div>

                <div class="field field--inline">
                    <label class="visually-hidden" for="tags">Filter by tag</label>
                    <input class="field__input" id="tags" name="tags" list="all-tags" value="{{ $activeTags->implode(', ') }}" placeholder="Tags">
                    <datalist id="all-tags">
                        @foreach ($allTags as $tag)
                            <option value="{{ $tag }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div class="field field--inline">
                    <label class="field__label field__label--compact" for="from">From</label>
                    <input class="field__input" id="from" type="date" name="from" value="{{ request('from') }}">
                </div>

                <div class="field field--inline">
                    <label class="field__label field__label--compact" for="to">To</label>
                    <input class="field__input" id="to" type="date" name="to" value="{{ request('to') }}">
                </div>

                <div class="filter-bar__actions">
                    <button type="submit" class="btn btn--primary btn--sm">Apply filters</button>
                    <a class="btn btn--ghost btn--sm" href="{{ route('albums.show', $album) }}">Clear filters</a>
                </div>
            </form>
        </section>

        <div class="filter-sheet-backdrop" data-filter-backdrop aria-hidden="true"></div>

        <button type="button" class="filter-sheet-trigger" data-filter-open aria-controls="filter-sheet">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            <span>Filters</span>
        </button>

        @if ($photos->isEmpty())
            <div class="empty-state">
                <p class="empty-state__title">No photos match</p>
                <p class="empty-state__text">Upload photos into this album using the drop zone, or adjust your filters.</p>
            </div>
        @else
            <div class="photo-grid" id="photo-grid">
                @foreach ($photos as $photo)
                    <x-photo-card :photo="$photo" :selectable="true"></x-photo-card>
                @endforeach
            </div>

            @if ($photos->hasMorePages())
                <div class="grid-sentinel" data-infinite-scroll
                     data-page-url="{{ route('photos.page') }}"
                     data-album="{{ $album->id }}"
                     data-next-page="{{ $photos->currentPage() + 1 }}"
                     data-has-more="true" aria-live="polite">
                    <span class="grid-sentinel__spinner" aria-hidden="true"></span>
                    <span class="grid-sentinel__text">Loading more photos&hellip;</span>
                </div>
            @endif
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
