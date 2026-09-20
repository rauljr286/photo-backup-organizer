@props(['albumId' => null])

<section class="dropzone" data-upload-zone data-url="{{ route('photos.store') }}" data-album="{{ $albumId }}" aria-labelledby="upload-heading">
    <h2 class="visually-hidden" id="upload-heading">Upload photos</h2>

    <div class="dropzone__area" id="drop-target" role="button" tabindex="0" aria-label="Upload photos. Press Enter or Space to choose files, or drag and drop photos here.">
        <svg class="dropzone__icon" viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12 16V4m0 0 4 4m-4-4L8 8M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>
        </svg>
        <p class="dropzone__title">Drag and drop photos here, or</p>
        <button type="button" class="btn btn--primary btn--sm" id="pick-files">Choose photos to upload</button>
        <p class="dropzone__hint">JPEG, PNG, WebP or GIF. Up to {{ config('photobackup.max_upload_mb', 20) }}MB each.</p>
    </div>

    <input class="visually-hidden" type="file" id="photo-input" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
    <input type="hidden" name="album_id" value="{{ $albumId }}">

    <ul class="upload-list" id="upload-list" aria-live="polite"></ul>
</section>