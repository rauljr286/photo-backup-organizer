<x-app-layout :title="'My photos'">
    <div class="dashboard">
        <x-hero-banner
            :greeting="'Welcome back, ' . \Illuminate\Support\Str::before(auth()->user()->name, ' ') . '!'"
            :eyebrow="'Your photo library'"
            :user="auth()->user()"
            :stats="[
                ['icon' => 'photo', 'value' => $photos->total(), 'label' => 'Photos', 'tint' => 'stat__icon--primary'],
                ['icon' => 'album', 'value' => $albums->count(), 'label' => 'Albums', 'tint' => 'stat__icon--accent'],
                ['icon' => 'storage', 'value' => $usedPercent . '%', 'label' => 'Storage used', 'note' => number_format($usedBytes / 1024 / 1024, 1) . ' of ' . number_format($limitBytes / 1024 / 1024, 0) . ' MB', 'meter' => $usedPercent, 'tint' => 'stat__icon--teal'],
                ['icon' => 'trash', 'value' => $trashedCount, 'label' => 'In trash', 'tint' => 'stat__icon--rose'],
            ]"
        ></x-hero-banner>

        <div class="dashboard__body">
            <x-storage-warning :user="auth()->user()"></x-storage-warning>

            <div class="dashboard__top">
                <div>
                    <h1 class="page-title">My photos</h1>
                    <p class="page-subtitle">Upload, organize and keep your photos safely backed up.</p>
                </div>
                <div class="dashboard__top-actions">
                    @if ($unbackedUpCount > 0)
                        <form method="POST" action="{{ route('backup.now') }}" class="inline-form">
                            @csrf
                            <button type="submit" class="btn btn--primary">
                                Back Up Now ({{ $unbackedUpCount }} pending)
                            </button>
                        </form>
                    @else
                        <span class="btn btn--success-static" aria-disabled="true">
                            All photos backed up
                        </span>
                    @endif
                    <a href="{{ route('albums.index') }}" class="btn btn--secondary">Manage albums</a>
                </div>
            </div>

            <x-upload-dropzone :album-id="$activeAlbum"></x-upload-dropzone>

            <section class="filter-bar" aria-labelledby="filter-heading">
                <h2 class="visually-hidden" id="filter-heading">Search and filter your photos</h2>
                <form method="GET" action="{{ route('dashboard') }}" class="filter-form" id="filter-form">
                    <input type="hidden" name="album" value="{{ $activeAlbum }}">

                    <div class="field field--inline">
                        <label class="visually-hidden" for="search">Search photos</label>
                        <input class="field__input" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Search by name, caption or tag&hellip;">
                    </div>

                    <div class="field field--inline">
                        <label class="visually-hidden" for="tags">Filter by tag</label>
                        <input class="field__input" id="tags" name="tags" list="all-tags" value="{{ $activeTags->implode(', ') }}" placeholder="Tags (comma separated)">
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
                        <a class="btn btn--ghost btn--sm" href="{{ route('dashboard') }}">Clear filters</a>
                    </div>
                </form>
            </section>

            <section class="photo-section" aria-labelledby="photos-heading">
                <h2 class="visually-hidden" id="photos-heading">Your photo grid</h2>

                @if ($photos->isEmpty() && $photos->total() === 0)
                    <div class="empty-state">
                        <span class="empty-state__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                        </span>
                        <p class="empty-state__title">No photos yet</p>
                        <p class="empty-state__text">Upload your first one to get started — your photos are kept private to your account.</p>
                        <div class="empty-state__actions">
                            <button type="button" class="btn btn--primary js-open-picker">Upload Photos</button>
                        </div>
                    </div>
                @elseif ($photos->isEmpty())
                    <div class="empty-state">
                        <p class="empty-state__title">No photos match your filters</p>
                        <p class="empty-state__text">Try clearing the search or filters to see more photos.</p>
                        <div class="empty-state__actions">
                            <a class="btn btn--ghost" href="{{ route('dashboard') }}">Clear filters</a>
                        </div>
                    </div>
                @else
                    <p class="photo-section__count">{{ $photos->total() }} photo(s)</p>
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
            </section>
        </div>
    </div>

    <div class="bulk-bar" id="bulk-bar" role="region" aria-label="Bulk actions" hidden>
        <p><strong id="bulk-count">0</strong> selected</p>
        <div class="bulk-bar__actions">
            <button type="button" class="btn btn--primary btn--sm" id="download-selected" data-url="{{ route('photos.download-selected') }}">Download selected</button>
            <button type="button" class="btn btn--primary btn--sm" id="delete-selected">Delete selected</button>
            <button type="button" class="btn btn--ghost btn--sm" id="clear-selected">Clear selection</button>
        </div>
    </div>
</x-app-layout>