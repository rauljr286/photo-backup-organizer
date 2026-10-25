<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Photo storage configuration
    |--------------------------------------------------------------------------
    |
    | cloud_disk: when set to a configured disk (e.g. "s3"), photos uploaded
    | through "Back Up Now" are mirrored there. Leave empty for local
    | development — the service then mirrors to local_mirror_disk instead so the
    | backup flow still works end to end.
    |
    */

    'cloud_disk' => env('PHOTOS_CLOUD_DISK'),

    'local_mirror_disk' => env('PHOTOS_LOCAL_MIRROR_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Photo disk
    |--------------------------------------------------------------------------
    |
    | The disk that stores the photo originals, the generated thumbnails and
    | the profile pictures. It defaults to the local "public" disk, which is
    | what local development uses. On serverless platforms (Vercel) the
    | container filesystem is ephemeral, so this has to point at a durable
    | bucket instead (e.g. "s3"). Every file is still streamed through the
    | application, so the bucket stays private.
    |
    */

    'photo_disk' => env('PHOTOS_DISK') ?: 'public',

    /*
    | Maximum size of a single uploaded photo, in megabytes.
    */
    'max_upload_mb' => env('PHOTOS_MAX_UPLOAD_MB', 20),

    /*
    |--------------------------------------------------------------------------
    | Direct (presigned) uploads
    |--------------------------------------------------------------------------
    |
    | When enabled, the browser uploads the photo straight to the bucket with a
    | short-lived presigned URL, so the file never travels through the
    | application server. That is only possible when the photo disk is a bucket
    | (an S3 disk), because a local disk cannot hand out upload URLs. With the
    | setting off, or with a local photo disk, uploads keep using the classic
    | server-side flow.
    |
    | direct_upload_url_ttl: how long the presigned upload URL stays valid.
    |
    | pending_ttl_minutes: how long a prepared upload may wait for the browser
    | to finish before it is considered expired and cleaned up.
    |
    */

    'direct_upload' => (bool) env('PHOTOS_DIRECT_UPLOAD', false),

    'direct_upload_url_ttl' => (int) env('PHOTOS_UPLOAD_URL_TTL', 300),

    'pending_ttl_minutes' => (int) env('PHOTOS_PENDING_TTL_MINUTES', 60),

    /*
    | Contact address shown in the in-app privacy policy.
    */
    'support_email' => env('PHOTOS_SUPPORT_EMAIL', 'support@example.com'),

    /*
    | Social profiles linked from the site footer. Replace the defaults with
    | your real profile URLs, or set a value to an empty string to hide its icon.
    */
    'social' => [
        'facebook' => env('PHOTOS_SOCIAL_FACEBOOK', 'https://www.facebook.com'),
        'instagram' => env('PHOTOS_SOCIAL_INSTAGRAM', 'https://www.instagram.com'),
        'twitter' => env('PHOTOS_SOCIAL_TWITTER', 'https://twitter.com'),
        'linkedin' => env('PHOTOS_SOCIAL_LINKEDIN', 'https://www.linkedin.com'),
    ],

];
