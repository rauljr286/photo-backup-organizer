@props(['albumId' => null, 'url' => null, 'private' => false])

@php
    $directUpload = \App\Support\DirectUpload::isEnabled();
@endphp

<section class="dropzone" id="upload" data-upload-zone data-url="{{ $url ?? route('photos.store') }}" data-album="{{ $albumId }}" data-private="{{ $private ? '1' : '0' }}" data-direct-upload="{{ $directUpload ? '1' : '0' }}" data-prepare-url="{{ $directUpload ? route('photos.uploads.prepare') : '' }}" data-complete-url="{{ $directUpload ? route('photos.uploads.complete') : '' }}" data-max-upload-mb="{{ config('photobackup.max_upload_mb', 20) }}" aria-labelledby="upload-heading">
    <h2 class="visually-hidden" id="upload-heading">Upload photos</h2>

    <div class="dropzone__area" id="drop-target" role="button" tabindex="0" aria-label="Upload photos. Press Enter or Space to choose files, or drag and drop photos here.">
        <span class="dropzone__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                <path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path>
                <path d="M12 12v6"></path>
                <path d="m9 15 3-3 3 3"></path>
            </svg>
        </span>

        <p class="dropzone__title">Drag and drop photos here, or</p>
        <button type="button" class="btn btn--primary btn--sm" id="pick-files">Choose photos to upload</button>
        <p class="dropzone__hint">JPEG, PNG, WebP or GIF. Up to {{ config('photobackup.max_upload_mb', 20) }}MB each.</p>
    </div>

    <input class="visually-hidden" type="file" id="photo-input" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
    <input class="visually-hidden" type="file" id="photo-camera-input" accept="image/*" capture="environment" multiple>
    <input type="hidden" name="album_id" value="{{ $albumId }}">

    <ul class="upload-list" id="upload-list" aria-live="polite"></ul>
</section>
