<x-app-layout :title="'My photos'">
    <div class="dashboard">
        <x-hero-banner
            :greeting="'Welcome back, ' . \Illuminate\Support\Str::before(auth()->user()->name, ' ') . '!'"
            :eyebrow="'Your photo library'"
            :subtitle="'All of your memories, safely backed up and organized in one place.'"

            :user="auth()->user()"
            :stats="[
                ['icon' => 'photo', 'value' => $activeCount, 'label' => 'Photos', 'tint' => 'stat__icon--primary'],
                ['icon' => 'album', 'value' => $albums->count(), 'label' => 'Albums', 'tint' => 'stat__icon--accent'],
                ['icon' => 'storage', 'value' => $usedPercent . '%', 'label' => 'Storage used', 'note' => number_format($usedBytes / 1024 / 1024, 1) . ' of ' . number_format($limitBytes / 1024 / 1024, 0) . ' MB', 'meter' => $usedPercent, 'tint' => 'stat__icon--teal'],
                ['icon' => 'archive', 'value' => $archivedCount, 'label' => 'Archived', 'tint' => 'stat__icon--rose'],
            ]"
        ></x-hero-banner>

        <div class="dashboard__body">
            <x-storage-warning :user="auth()->user()"></x-storage-warning>

            <x-upload-dropzone :album-id="$activeAlbum"></x-upload-dropzone>

            <section class="filter-bar" aria-labelledby="filter-heading">
                <h2 class="filter-bar__title" id="filter-heading">Filter your photos</h2>
                <form method="GET" action="{{ route('dashboard') }}" class="filter-form" id="filter-form">
                    <input type="hidden" name="album" value="{{ $activeAlbum }}">

                    <div class="field field--inline">
                        <label class="field__label field__label--compact" for="search">Search</label>
                        <input class="field__input" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Search by name, date or tags">
                    </div>

                    <div class="field field--inline">
                        <label class="field__label field__label--compact" for="tags">Tags</label>
                        <input class="field__input" id="tags" name="tags" list="all-tags" value="{{ $activeTags->implode(', ') }}" placeholder="e.g. family, beach">
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
                        <button type="submit" class="btn btn--primary btn--sm">Apply</button>
                        <a class="btn btn--ghost btn--sm" href="{{ route('dashboard') }}">Reset</a>
                    </div>
                </form>
            </section>

            <div class="section-head">
                <span class="section-head__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <path d="m21 15-5-5L5 21"></path>
                    </svg>
                </span>
                <div class="section-head__text">
                    <h1 class="section-head__title">My photos</h1>
                    <p class="section-head__subtitle">{{ number_format($photos->total()) }} {{ \Illuminate\Support\Str::plural('photo', $photos->total()) }}</p>
                </div>
                <div class="section-head__actions dashboard__top-actions">
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

            <section class="photo-section" id="photo-section" aria-labelledby="photos-heading"
                data-latest-url="{{ route('photos.latest') }}"
                data-latest-id="{{ $photos->getCollection()->max('id') ?? 0 }}">
                <h2 class="visually-hidden" id="photos-heading">Your photo grid</h2>

                @if ($photos->isEmpty() && $photos->total() === 0)
                    <div class="empty-state empty-state--rich">
                        <span class="empty-state__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                        </span>
                        <p class="empty-state__title">Your library is ready</p>
                        <p class="empty-state__text">Upload your first photo and we&rsquo;ll tuck it away safely &mdash; private to your account, organized and backed up.</p>
                        <div class="empty-state__actions">
                            <button type="button" class="btn btn--primary js-open-picker">Upload Photos</button>
                            <a class="btn btn--ghost" href="{{ route('albums.index') }}">Create an album</a>
                        </div>

                        <ul class="empty-state__tips" aria-label="Getting started tips">
                            <li class="empty-tip">
                                <span class="empty-tip__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <path d="M7 9l5-5 5 5"></path>
                                        <path d="M12 4v12"></path>
                                    </svg>
                                </span>
                                <span class="empty-tip__label">Upload from any device</span>
                            </li>
                            <li class="empty-tip">
                                <span class="empty-tip__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                </span>
                                <span class="empty-tip__label">Organize into albums</span>
                            </li>
                            <li class="empty-tip">
                                <span class="empty-tip__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                        <path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path>
                                    </svg>
                                </span>
                                <span class="empty-tip__label">Back up with confidence</span>
                            </li>
                        </ul>
                    </div>
                @elseif ($photos->isEmpty())
                    <div class="empty-state empty-state--rich">
                        <span class="empty-state__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </svg>
                        </span>
                        <p class="empty-state__title">No photos match your filters</p>
                        <p class="empty-state__text">Try clearing the search or filters to see more photos.</p>
                        <div class="empty-state__actions">
                            <a class="btn btn--ghost" href="{{ route('dashboard') }}">Clear filters</a>
                        </div>
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
            </section>
        </div>
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
