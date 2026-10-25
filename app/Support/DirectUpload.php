<?php

namespace App\Support;

/**
 * Direct (presigned) uploads: the browser sends the photo straight to the
 * bucket with a short-lived signed URL, so the file never travels through
 * the application server.
 *
 * That is only possible when the photo disk is a bucket, because a local disk
 * cannot hand out upload URLs. With a local photo disk - or with the setting
 * switched off - the classic server-side upload is used instead.
 */
class DirectUpload
{
    /**
     * Whether photos are uploaded straight to the bucket.
     */
    public static function isEnabled(): bool
    {
        if (! (bool) config('photobackup.direct_upload', false)) {
            return false;
        }

        $disk = (string) config('photobackup.photo_disk', 'public');

        return config("filesystems.disks.{$disk}.driver") === 's3';
    }
}
