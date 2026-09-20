<x-app-layout :title="'Trash'">
    <div class="dashboard">
        <div class="dashboard__top">
            <div>
                <h1 class="page-title">Trash</h1>
                <p class="page-subtitle">
                    Photos stay here for {{ $retentionDays }} days, then they are permanently deleted.
                </p>
            </div>
        </div>

        @if ($photos->isEmpty())
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <path d="M3 6h18"></path>
                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </span>
                <p class="empty-state__title">Trash is empty</p>
                <p class="empty-state__text">Nothing here yet — photos you delete will stay in Trash for a while before they are permanently removed.</p>
            </div>
        @else
            <div class="photo-grid" id="trash-grid">
                @foreach ($photos as $photo)
                    <article class="photo-card">
                        <div class="photo-card__media">
                            <img class="photo-card__img" src="{{ $photo->thumbnailUrl() }}" alt="{{ $photo->description() }}" loading="lazy">
                        </div>
                        <figcaption class="photo-card__caption">
                            <span class="photo-card__date">Deleted {{ $photo->deleted_at->format('M j, Y') }}</span>
                            <span class="photo-card__size">{{ number_format($photo->size_bytes / 1024, 0) }} KB</span>
                        </figcaption>
                        <div class="photo-card__actions">
                            <form method="POST" action="{{ route('photos.restore', $photo) }}" class="inline-form">
                                @csrf
                                <button type="submit" class="btn btn--ghost btn--sm">Restore</button>
                            </form>
                            <button type="button" class="btn btn--danger-ghost btn--sm js-purge-photo"
                                data-name="{{ $photo->original_filename }}"
                                data-url="{{ route('photos.purge', $photo) }}">Delete forever</button>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Single-photo purge confirmation (Yes/No, no typed word) --}}
            <dialog class="dialog" id="purge-dialog" aria-labelledby="purge-dialog-title" aria-describedby="purge-dialog-desc">
                <h2 class="dialog__title" id="purge-dialog-title">Delete forever?</h2>
                <p id="purge-dialog-desc">
                    <strong id="purge-dialog-name"></strong> will be permanently deleted. This cannot be undone.
                </p>
                <form method="POST" id="purge-form">
                    @csrf
                    @method('DELETE')
                    <div class="dialog__actions">
                        <button type="button" class="btn btn--ghost js-dialog-cancel" data-close="purge-dialog">Cancel</button>
                        <button type="submit" class="btn btn--danger">Yes, delete forever</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>

    @push('scripts')
    <script>
    (function () {
        const purgeForm = document.getElementById('purge-form');
        const purgeName = document.getElementById('purge-dialog-name');

        function openPurge(name, url) {
            if (!purgeForm || !url) return;
            purgeName.textContent = `"${name}"`;
            purgeForm.action = url;
            document.getElementById('purge-dialog')?.showModal();
        }

        document.querySelectorAll('.js-purge-photo').forEach((btn) => {
            btn.addEventListener('click', () => {
                openPurge(btn.dataset.name || 'this photo', btn.dataset.url);
            });
        });

        document.querySelectorAll('.js-dialog-cancel').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.dataset.close)?.close();
            });
        });
    })();
    </script>
    @endpush
</x-app-layout>
