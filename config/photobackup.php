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
    | Number of days a photo stays in the trash before it is permanently
    | deleted (see the purge-trash scheduled command).
    */
    'trash_retention_days' => env('PHOTOS_TRASH_RETENTION_DAYS', 30),

    /*
    | Maximum size of a single uploaded photo, in megabytes.
    */
    'max_upload_mb' => env('PHOTOS_MAX_UPLOAD_MB', 20),

    /*
    | Contact address shown in the in-app privacy policy.
    */
    'support_email' => env('PHOTOS_SUPPORT_EMAIL', 'support@example.com'),

];
