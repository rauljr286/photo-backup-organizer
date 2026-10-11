<x-app-layout :title="'Photo details'">
    <div class="photo-page">
        <div class="dashboard__top">
            <div>
                <h1 class="page-title">{{ $photo->original_filename }}</h1>
                @if ($photo->isArchived())
                    <p class="page-subtitle">This photo is archived.</p>
                @endif
            </div>

            <div class="dashboard__top-actions">
                <a class="btn btn--primary" href="{{ route('photos.download', $photo) }}">Download photo</a>

                @if ($photo->isArchived())
                    <form method="POST" action="{{ route('photos.unarchive', $photo) }}" class="inline-form">
                        @csrf
                        <button type="submit" class="btn btn--secondary">Restore from archive</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('photos.archive', $photo) }}" class="inline-form js-archive-photo-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger-ghost">Archive</button>
                    </form>
                @endif
            </div>
        </div>

        <section class="viewer" aria-labelledby="viewer-heading">
            <h2 class="visually-hidden" id="viewer-heading">Zoomable photo preview</h2>

            <div class="viewer__stage" id="zoom-stage">
                <img id="zoom-image" class="viewer__image" src="{{ $photo->url() }}?v={{ $photo->updated_at?->timestamp ?? 0 }}" alt="{{ $photo->description() }}"
                     data-zoom-level="1">

                @if ($prevPhoto)
                    <a class="viewer__nav viewer__nav--prev" href="{{ route('photos.show', $prevPhoto) }}"
                       aria-label="Previous photo">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="15 18 9 12 15 6"/></svg>
                    </a>
                @endif

                @if ($nextPhoto)
                    <a class="viewer__nav viewer__nav--next" href="{{ route('photos.show', $nextPhoto) }}"
                       aria-label="Next photo">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                @endif
            </div>

            <div class="viewer__controls">
                <button type="button" class="btn btn--ghost btn--sm" id="zoom-out" aria-label="Zoom out">Zoom out</button>
                <button type="button" class="btn btn--ghost btn--sm" id="zoom-in" aria-label="Zoom in">Zoom in</button>
                <button type="button" class="btn btn--ghost btn--sm" id="zoom-reset" aria-label="Reset zoom level">Reset zoom</button>
                <span class="viewer__zoom-level" id="zoom-level" role="status">100%</span>
            </div>
            <p class="viewer__hint">Tip: browse photos with the arrow buttons or the &larr; &rarr; arrow keys.</p>
        </section>

        <div class="photo-meta-grid">
            <section class="panel" aria-labelledby="details-heading">
                <h2 class="panel__heading" id="details-heading">Photo details</h2>
                <dl class="detail-list">
                    <div class="detail-list__row">
                        <dt>Taken</dt>
                        <dd>{{ $photo->taken_at ? $photo->taken_at->format('F j, Y') : 'Unknown (no EXIF date)' }}</dd>
                    </div>
                    <div class="detail-list__row">
                        <dt>Uploaded</dt>
                        <dd>{{ $photo->created_at->format('F j, Y') }}</dd>
                    </div>
                    <div class="detail-list__row">
                        <dt>Size</dt>
                        <dd>{{ number_format($photo->size_bytes / 1024, 0) }} KB</dd>
                    </div>
                    <div class="detail-list__row">
                        <dt>Type</dt>
                        <dd>{{ $photo->mime_type ?? 'Unknown' }}</dd>
                    </div>
                    <div class="detail-list__row">
                        <dt>Cloud backup</dt>
                        <dd>
                            @if ($photo->isBackedUp())
                                Backed up on {{ $photo->backed_up_at->format('M j, Y') }}
                            @else
                                {{ $photo->isArchived() ? 'Skipped (archived)' : 'Not yet backed up' }}
                            @endif
                        </dd>
                    </div>
                    <div class="detail-list__row">
                        <dt>Albums</dt>
                        <dd>
                            @forelse ($photoAlbums as $albumId => $name)
                                <a class="link" href="{{ route('albums.show', $albumId) }}">{{ $name }}</a>{{ ! $loop->last ? ', ' : '' }}
                            @empty
                                Not in any album
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="panel" aria-labelledby="edit-heading">
                <h2 class="panel__heading" id="edit-heading">Description &amp; tags</h2>
                <form method="POST" action="{{ route('photos.update', $photo) }}" class="form">
                    @csrf
                    @method('PATCH')

                    <div class="field">
                        <label class="field__label" for="alt_text">Alt text (photo description)</label>
                        <textarea class="field__input" id="alt_text" name="alt_text" rows="3"
                                  aria-describedby="alt-hint">{{ old('alt_text', $photo->alt_text) }}</textarea>
                        <p class="field__hint" id="alt-hint">
                            Describes the photo for screen readers. Leave blank to use the automatic description.
                        </p>
                    </div>

                    <div class="field">
                        <label class="field__label" for="tags">Tags</label>
                        <input class="field__input" id="tags" name="tags" type="text" value="{{ old('tags', collect($photo->tags ?? [])->implode(', ')) }}"
                               aria-describedby="tags-hint">
                        <p class="field__hint" id="tags-hint">Comma-separated, e.g. &ldquo;vacation, family, beach&rdquo;.</p>
                    </div>

                    <button type="submit" class="btn btn--primary">Save description</button>
                </form>
            </section>

            <section class="panel" aria-labelledby="organize-heading">
                <h2 class="panel__heading" id="organize-heading">Add to album</h2>

                <div class="field">
                    <p class="field__label" id="organize-hint">Choose an album to file this photo under, or
                        <a class="link" href="{{ route('albums.index') }}">create a new album</a>.</p>
                </div>

                @if ($albums->isEmpty())
                    <p>You don&rsquo;t have any albums yet. <a class="link" href="{{ route('albums.index') }}">Create one</a>.</p>
                @else
                    <ul class="album-pick-list__items">
                        @foreach ($albums as $album)
                            <li class="album-pick-list__row">
                                <span class="album-pick-list__name">{{ $album->name }}</span>
                                @if ($photoAlbums->contains($album->id))
                                    <form method="POST" action="{{ route('albums.photos.remove', $album) }}" class="inline-form">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="photo_id" value="{{ $photo->id }}">
                                        <button type="submit" class="btn btn--danger-ghost btn--sm"
                                                aria-label="Remove this photo from {{ $album->name }}">Remove from album</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('albums.photos.add', $album) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="photo_id" value="{{ $photo->id }}">
                                        <button type="submit" class="btn btn--ghost btn--sm">Add to &ldquo;{{ $album->name }}&rdquo;</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>