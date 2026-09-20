<x-app-layout :title="'Photo details'">
    <div class="photo-page">
        <div class="dashboard__top">
            <div>
                <a class="link" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}">&larr; Back to photos</a>
                <h1 class="page-title">{{ $photo->original_filename }}</h1>
                @if ($photo->trashed())
                    <p class="page-subtitle">This photo is in the trash.</p>
                @endif
            </div>

            <div class="dashboard__top-actions">
                <a class="btn btn--primary" href="{{ route('photos.download', $photo) }}">Download photo</a>

                @if ($photo->trashed())
                    <form method="POST" action="{{ route('photos.restore', $photo) }}" class="inline-form">
                        @csrf
                        <button type="submit" class="btn btn--secondary">Restore from trash</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('photos.trash', $photo) }}" class="inline-form js-trash-photo-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger-ghost">Move to trash</button>
                    </form>
                @endif
            </div>
        </div>

        <section class="viewer" aria-labelledby="viewer-heading">
            <h2 class="visually-hidden" id="viewer-heading">Zoomable photo preview</h2>

            <div class="viewer__stage" id="zoom-stage">
                <img id="zoom-image" class="viewer__image" src="{{ $photo->url() }}" alt="{{ $photo->description() }}"
                     data-zoom-level="1">
            </div>

            <div class="viewer__controls">
                <button type="button" class="btn btn--ghost btn--sm" id="zoom-out" aria-label="Zoom out">Zoom out</button>
                <button type="button" class="btn btn--ghost btn--sm" id="zoom-in" aria-label="Zoom in">Zoom in</button>
                <button type="button" class="btn btn--ghost btn--sm" id="zoom-reset" aria-label="Reset zoom level">Reset zoom</button>
                <span class="viewer__zoom-level" id="zoom-level" role="status">100%</span>
            </div>
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
                                {{ $photo->trashed() ? 'Skipped (in trash)' : 'Not yet backed up' }}
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

        @if ($photo->trashed())
            <section class="panel panel--danger" aria-labelledby="purge-heading">
                <h2 class="panel__heading" id="purge-heading">Permanently delete</h2>
                <p>
                    This deletes the photo forever after the {{ config('photobackup.trash_retention_days', 30) }}-day trash window. Type
                    <strong>DELETE</strong> to confirm.
                </p>
                <form method="POST" action="{{ route('photos.purge', $photo) }}" class="form form--row" id="purge-form">
                    @csrf
                    @method('DELETE')
                    <div class="field field--grow">
                        <label class="visually-hidden" for="confirm_text">Type DELETE to confirm</label>
                        <input class="field__input" id="confirm_text" type="text" name="confirm_text" autocomplete="off" required
                               placeholder="Type DELETE to confirm">
                    </div>
                    <button type="submit" class="btn btn--danger">Delete permanently</button>
                </form>
            </section>
        @endif
    </div>
</x-app-layout>