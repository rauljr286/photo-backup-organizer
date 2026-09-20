<x-app-layout :title="$album->name">
    <div class="dashboard">
        <div class="dashboard__top">
            <div>
                <a class="link" href="{{ route('albums.index') }}">&larr; All albums</a>
                <h1 class="page-title">{{ $album->name }}</h1>
                <p class="page-subtitle">{{ $photos->total() }} photo(s) in this album &middot; created {{ $album->created_at?->format('M j, Y') }}</p>
            </div>
            <div class="dashboard__top-actions">
                <a class="btn btn--primary" href="{{ route('albums.download', $album) }}">Download ZIP</a>
                <a class="btn btn--secondary" href="{{ route('dashboard', ['album' => $album->id]) }}">Open in photo grid</a>
            </div>
        </div>

        <x-upload-dropzone :album-id="$album->id"></x-upload-dropzone>

        <section class="filter-bar" aria-labelledby="filter-heading">
            <h2 class="visually-hidden" id="filter-heading">Filter photos in this album</h2>
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

            @if ($photos->hasPages())
                <nav class="pagination" aria-label="Photo pages">
                    {{ $photos->withQueryString()->links() }}
                </nav>
            @endif
        @endif
    </div>
</x-app-layout>