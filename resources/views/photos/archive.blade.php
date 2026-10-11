<x-app-layout :title="'Archive'">
    <div class="dashboard">
        <div class="dashboard__top">
            <div>
                <h1 class="page-title">Archive</h1>
                <p class="page-subtitle">
                    Archived photos are kept safely and hidden from your photo grid until you restore them.
                </p>
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
            <div class="photo-grid" id="archive-grid">
                @foreach ($photos as $photo)
                    <article class="photo-card">
                        <div class="photo-card__media">
                            <img class="photo-card__img" src="{{ $photo->thumbnailUrl() }}" alt="{{ $photo->description() }}" loading="lazy">
                        </div>
                        <figcaption class="photo-card__caption">
                            <span class="photo-card__date">Archived {{ $photo->archived_at?->format('M j, Y') }}</span>
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
</x-app-layout>