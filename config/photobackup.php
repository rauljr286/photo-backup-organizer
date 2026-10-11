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
    | Maximum size of a single uploaded photo, in megabytes.
    */
    'max_upload_mb' => env('PHOTOS_MAX_UPLOAD_MB', 20),

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
