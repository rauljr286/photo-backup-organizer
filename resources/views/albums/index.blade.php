<x-app-layout :title="'Albums'">
    <div class="albums">
        <div class="dashboard__top">
            <div>
                <h1 class="page-title">Albums</h1>
                <p class="page-subtitle">Group related photos into albums you can browse and download.</p>
            </div>
        </div>

        <section class="panel" aria-labelledby="new-album-heading">
            <h2 class="panel__heading" id="new-album-heading">Create a new album</h2>
            <form method="POST" action="{{ route('albums.store') }}" class="form form--row">
                @csrf
                <div class="field field--grow">
                    <label class="visually-hidden" for="album-name">Album name</label>
                    <input class="field__input" id="album-name" type="text" name="name" placeholder="e.g. Summer 2025" required maxlength="120">
                </div>
                <button type="submit" class="btn btn--primary">Create New Album</button>
            </form>
        </section>

        @if ($albums->isEmpty())
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                </span>
                <p class="empty-state__title">You haven&rsquo;t created any albums yet</p>
                <p class="empty-state__text">Group related photos into albums you can flip through and download as a ZIP.</p>
                <div class="empty-state__actions">
                    <button type="button" class="btn btn--primary js-focus-album-name">Create Album</button>
                </div>
            </div>
        @else
            <div class="album-grid">
                @foreach ($albums as $album)
                    <article class="album-card">
                        <a class="album-card__link" href="{{ route('albums.show', $album) }}">
                            <h2 class="album-card__name">{{ $album->name }}</h2>
                            <p class="album-card__count">
                                {{ $album->photos_count }} photo(s)
                            </p>
                        </a>

                        <div class="album-card__actions">
                            <a class="btn btn--ghost btn--sm" href="{{ route('albums.download', $album) }}">Download ZIP</a>
                            <button type="button" class="btn btn--ghost btn--sm js-rename-album"
                                    data-id="{{ $album->id }}"
                                    data-name="{{ $album->name }}"
                                    data-url="{{ route('albums.update', $album) }}">Rename</button>
                            <button type="button" class="btn btn--danger-ghost btn--sm js-delete-album"
                                    data-id="{{ $album->id }}"
                                    data-name="{{ $album->name }}"
                                    data-url="{{ route('albums.destroy', $album) }}">Delete album</button>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Rename dialog --}}
            <dialog class="dialog" id="rename-dialog" aria-labelledby="rename-dialog-title">
                <form method="POST" id="rename-form" class="form">
                    @csrf
                    @method('PUT')
                    <h2 class="dialog__title" id="rename-dialog-title">Rename album</h2>
                    <div class="field">
                        <label class="field__label" for="rename-name">Album name</label>
                        <input class="field__input" id="rename-name" type="text" name="name" required maxlength="120">
                    </div>
                    <div class="dialog__actions">
                        <button type="button" class="btn btn--ghost js-dialog-close" data-target="rename-dialog">Cancel</button>
                        <button type="submit" class="btn btn--primary">Save new name</button>
                    </div>
                </form>
            </dialog>

            {{-- Delete confirmation dialog --}}
            <dialog class="dialog" id="delete-dialog" aria-labelledby="delete-dialog-title" aria-describedby="delete-dialog-desc">
                <h2 class="dialog__title" id="delete-dialog-title">Delete album?</h2>
                <p id="delete-dialog-desc">
                    Deleting <strong id="delete-dialog-name"></strong> removes the album only.
                    The photos inside it will stay in your library.
                </p>
                <form method="POST" id="delete-form" class="dialog__actions">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn--ghost js-dialog-close" data-target="delete-dialog">Cancel</button>
                    <button type="submit" class="btn btn--danger">Delete album</button>
                </form>
            </dialog>
        @endif
    </div>
</x-app-layout>