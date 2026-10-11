@props(['photo', 'selectable' => false])

<figure class="photo-card" data-photo-id="{{ $photo->id }}" data-archive-url="{{ route('photos.archive', $photo) }}" data-restore-url="{{ route('photos.unarchive', $photo) }}">
    <a class="photo-card__media" href="{{ route('photos.show', $photo) }}" aria-label="Open {{ $photo->description() }}">
        <img class="photo-card__img" src="{{ $photo->thumbnailUrl() }}" alt="{{ $photo->description() }}" loading="lazy">
        @if (! $photo->isBackedUp())
            <span class="photo-card__flag" title="Not backed up to cloud yet">Cloud backup pending</span>
        @endif
    </a>

    <figcaption class="photo-card__caption">
        <span class="photo-card__date">
            {{ $photo->taken_at ? $photo->taken_at->format('M j, Y') : 'Taken date unknown' }}
        </span>
        <span class="photo-card__size">{{ number_format($photo->size_bytes / 1024, 0) }} KB</span>
    </figcaption>

    @if ($selectable)
        <div class="photo-card__actions">
            <label class="field__check photo-card__check">
                <input class="field__checkbox js-select-photo" type="checkbox" name="selected[]" value="{{ $photo->id }}" aria-label="Select {{ $photo->description() }}">
            </label>
        </div>
    @endif
</figure>