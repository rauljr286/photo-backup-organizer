<x-app-layout :title="'Archive'">
    <div class="dashboard">
        <div class="section-head">
            <span class="section-head__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                    <rect x="2" y="3" width="20" height="5" rx="1"></rect>
                    <path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"></path>
                    <path d="M10 12h4"></path>
                </svg>
            </span>
            <div class="section-head__text">
                <h1 class="section-head__title">Archive</h1>
            </div>
        </div>

        @if ($photos->isEmpty())
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <path d="M9 14h6"></path>
                    </svg>
                </span>
                <p class="empty-state__title">Archive is empty</p>
                <p class="empty-state__text">Nothing here yet — photos you archive will move to this page until you restore them.</p>
            </div>
        @else
            <div class="photo-grid photo-grid--archive" id="photo-grid">
                @foreach ($photos as $photo)
                    <article class="photo-card photo-card--archive" data-photo-id="{{ $photo->id }}" data-restore-url="{{ route('photos.unarchive', $photo) }}">
                        <a class="photo-card__media" href="{{ route('photos.show', $photo) }}" aria-label="Open {{ $photo->description() }}">
                            <img class="photo-card__img" src="{{ $photo->thumbnailUrl() }}" alt="{{ $photo->description() }}" loading="lazy">
                        </a>

                        <label class="visually-hidden">
                            <input class="field__checkbox js-select-photo" type="checkbox" name="selected[]" value="{{ $photo->id }}" aria-label="Select {{ $photo->description() }}">
                        </label>

                        {{-- Check button removed --}}

                        <figcaption class="photo-card__caption">
                            <span class="photo-card__date">
                                Archived {{ $photo->archived_at ? $photo->archived_at->format('M j, Y') : ($photo->taken_at ? $photo->taken_at->format('M j, Y') : 'Taken date unknown') }}
                            </span>
                            <span class="photo-card__size">{{ number_format($photo->size_bytes / 1024, 0) }} KB</span>
                        </figcaption>

                        <div class="photo-card__actions">
                            <form method="POST" action="{{ route('photos.unarchive', $photo) }}" class="inline-form">
                                @csrf
                                <button type="submit" class="btn btn--ghost btn--sm">Restore</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bulk-bar" id="bulk-bar" role="region" aria-label="Bulk actions" hidden>
        <p><strong id="bulk-count">0</strong> selected</p>
        <div class="bulk-bar__actions">
            <button type="button" class="btn btn--primary btn--sm" id="restore-selected">Restore selected</button>
            <button type="button" class="btn btn--ghost btn--sm" id="clear-selected">Clear selection</button>
        </div>
    </div>
</x-app-layout>
