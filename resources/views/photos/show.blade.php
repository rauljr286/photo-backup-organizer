<x-app-layout :title="$photo->original_filename" body-class="pd-page">
    <div class="pd">
        {{-- Title row --}}
        <div class="pd__top">
            <h1 class="pd__title">{{ $photo->original_filename }}</h1>

            <div class="pd__actions">
                <a class="pd-btn pd-btn--primary" href="{{ route('photos.download', $photo) }}">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Download photo</span>
                </a>

                @if ($photo->isPrivate())
                    <span class="pd-btn pd-btn--ghost" aria-disabled="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Private</span>
                    </span>
                @elseif ($photo->isArchived())
                    <form method="POST" action="{{ route('photos.unarchive', $photo) }}" class="pd-inline-form">
                        @csrf
                        <button type="submit" class="pd-btn pd-btn--ghost">Unarchive</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('photos.archive', $photo) }}" class="pd-inline-form js-archive-photo-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="pd-btn pd-btn--ghost">Archive</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="pd__grid">
            {{-- Left column: viewer + add to album --}}
            <div class="pd__col pd__col--main">
                <section class="pd-viewer" aria-labelledby="viewer-heading">
                    <h2 class="visually-hidden" id="viewer-heading">Zoomable photo preview</h2>

                    <div class="pd-viewer__stage" id="zoom-stage">
                        <img id="zoom-image" class="pd-viewer__image"
                             src="{{ $photo->url() }}?v={{ $photo->updated_at?->timestamp ?? 0 }}"
                             alt="{{ $photo->description() }}" data-zoom-level="1">

                        @if ($prevPhoto)
                            <a class="viewer__nav viewer__nav--prev pd-viewer__nav pd-viewer__nav--prev"
                               href="{{ route('photos.show', $prevPhoto) }}" aria-label="Previous photo">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="15 18 9 12 15 6"/></svg>
                            </a>
                        @endif

                        @if ($nextPhoto)
                            <a class="viewer__nav viewer__nav--next pd-viewer__nav pd-viewer__nav--next"
                               href="{{ route('photos.show', $nextPhoto) }}" aria-label="Next photo">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                        @endif
                    </div>

                    <div class="pd-zoom">
                        <button type="button" class="pd-zoom__btn" id="zoom-out">Zoom out</button>
                        <button type="button" class="pd-zoom__btn" id="zoom-in">Zoom in</button>
                        <button type="button" class="pd-zoom__btn" id="zoom-reset">Reset zoom</button>

                        @if ($photo->isRotatable())
                            <form method="POST" action="{{ route('photos.rotate', $photo) }}" class="pd-inline-form">
                                @csrf
                                <input type="hidden" name="direction" value="left">
                                <button type="submit" class="pd-zoom__btn pd-zoom__btn--icon" aria-label="Rotate 90 degrees left">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('photos.rotate', $photo) }}" class="pd-inline-form">
                                @csrf
                                <input type="hidden" name="direction" value="right">
                                <button type="submit" class="pd-zoom__btn pd-zoom__btn--icon" aria-label="Rotate 90 degrees right">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                </button>
                            </form>
                        @endif

                        <span class="pd-zoom__level" id="zoom-level" role="status" aria-live="polite" aria-label="Zoom level 100 percent">100%</span>
                    </div>

                    <p class="pd-tip">
                        Tip: browse photos with the arrow buttons or the
                        <kbd class="pd-kbd">&larr;</kbd> <kbd class="pd-kbd">&rarr;</kbd> arrow keys.
                    </p>
                </section>

                @unless ($photo->isPrivate())
                <section class="pd-card" id="albums" aria-labelledby="album-heading">
                    <h2 class="pd-card__title" id="album-heading">Add to album</h2>
                    <p class="pd-card__help">
                        Choose an album to file this photo under, or
                        <a class="pd-link" href="{{ route('albums.index') }}">create a new album.</a>
                    </p>

                    @if ($albums->isEmpty())
                        <p class="pd-card__help">You don&rsquo;t have any albums yet.</p>
                    @else
                        <ul class="pd-albums">
                            @foreach ($albums as $album)
                                @php($inAlbum = $photoAlbums->contains($album->id))
                                <li class="pd-album">
                                    <span class="pd-album__name">{{ $album->name }}</span>

                                    @if ($inAlbum)
                                        <form method="POST" action="{{ route('albums.photos.remove', $album) }}" class="pd-inline-form">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="photo_id" value="{{ $photo->id }}">
                                            <button type="submit" class="pd-album__btn pd-album__btn--in"
                                                    aria-label="Remove this photo from {{ $album->name }}">In &ldquo;{{ $album->name }}&rdquo;</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('albums.photos.add', $album) }}" class="pd-inline-form">
                                            @csrf
                                            <input type="hidden" name="photo_id" value="{{ $photo->id }}">
                                            <button type="submit" class="pd-album__btn">Add to &ldquo;{{ $album->name }}&rdquo;</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
                @endunless
            </div>

            {{-- Right column: photo details + details & tags --}}
            <div class="pd__col pd__col--side">
                <section class="pd-card" aria-labelledby="details-heading">
                    <h2 class="pd-card__title" id="details-heading">Photo details</h2>

                    <dl class="pd-details">
                        <div class="pd-details__row">
                            <dt>Taken</dt>
                            <dd>{{ $photo->taken_at ? $photo->taken_at->format('F j, Y') : 'Unknown' }}</dd>
                        </div>
                        <div class="pd-details__row">
                            <dt>Uploaded</dt>
                            <dd>{{ $photo->created_at->format('F j, Y') }}</dd>
                        </div>
                        <div class="pd-details__row">
                            <dt>Size</dt>
                            <dd>{{ number_format($photo->size_bytes / 1024, 0) }} KB</dd>
                        </div>
                        <div class="pd-details__row">
                            <dt>Type</dt>
                            <dd>{{ $photo->mime_type ?? 'Unknown' }}</dd>
                        </div>
                        <div class="pd-details__row">
                            <dt>Cloud backup</dt>
                            <dd>
                                @if ($photo->isBackedUp())
                                    Backed up on {{ $photo->backed_up_at->format('M j, Y') }}
                                @else
                                    Not backed up yet
                                @endif
                            </dd>
                        </div>
                        <div class="pd-details__row">
                            <dt>Albums</dt>
                            <dd>
                                @if ($photo->isPrivate())
                                    Private gallery &mdash; not filed in albums
                                @else
                                    @php($albumNames = $photo->albums()->orderBy('name')->pluck('name'))
                                    @if ($albumNames->isEmpty())
                                        Not in any album
                                    @else
                                        {{ $albumNames->implode(', ') }}
                                    @endif
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="pd-card" id="details" aria-labelledby="edit-heading">
                    <h2 class="pd-card__title" id="edit-heading">Details &amp; tags</h2>

                    <form method="POST" action="{{ route('photos.update', $photo) }}" class="pd-form">
                        @csrf
                        @method('PATCH')

                        <div class="pd-field">
                            <label class="pd-field__label" for="original_filename">Filename</label>
                            <input class="pd-field__input" id="original_filename" name="original_filename" type="text"
                                   value="{{ old('original_filename', $photo->original_filename) }}" aria-describedby="filename-hint">
                            <p class="pd-field__hint" id="filename-hint">Rename how this photo appears and how it will be downloaded.</p>
                        </div>

                        <div class="pd-field">
                            <label class="pd-field__label" for="alt_text">Alt text (photo description)</label>
                            <textarea class="pd-field__input pd-field__input--area" id="alt_text" name="alt_text"
                                      aria-describedby="alt-hint">{{ old('alt_text', $photo->alt_text) }}</textarea>
                            <p class="pd-field__hint" id="alt-hint">Describes the photo for screen readers.</p>
                        </div>

                        <div class="pd-field">
                            <label class="pd-field__label" for="tags">Tags</label>
                            <input class="pd-field__input" id="tags" name="tags" type="text"
                                   value="{{ old('tags', collect($photo->tags ?? [])->implode(', ')) }}"
                                   placeholder="Tags (comma separated)">
                        </div>

                        <button type="submit" class="pd-save">Save changes</button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
