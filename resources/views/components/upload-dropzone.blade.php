@props(['albumId' => null, 'url' => null])

<section class="dropzone" id="upload" data-upload-zone data-url="{{ $url ?? route('photos.store') }}" data-album="{{ $albumId }}" aria-labelledby="upload-heading">
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

    <div class="dropzone__phone-actions">
        <button type="button" class="btn btn--primary dropzone__phone-btn" id="pick-files-phone">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Choose photos
        </button>
        <button type="button" class="btn btn--secondary dropzone__phone-btn" id="take-photo">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                <circle cx="12" cy="13" r="4"></circle>
            </svg>
            Take photo
        </button>
    </div>

    <input class="visually-hidden" type="file" id="photo-input" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
    <input class="visually-hidden" type="file" id="photo-camera-input" accept="image/*" capture="environment" multiple>
    <input type="hidden" name="album_id" value="{{ $albumId }}">

    <ul class="upload-list" id="upload-list" aria-live="polite"></ul>
</section>