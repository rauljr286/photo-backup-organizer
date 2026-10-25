<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesDirectUploads;
use App\Http\Controllers\Controller;
use App\Support\PrivateGallery;
use Illuminate\Http\Request;

/**
 * The direct (presigned) upload for the browser: the photo is sent straight
 * to the bucket, so it never travels through the application server.
 */
class DirectUploadController extends Controller
{
    use HandlesDirectUploads;

    /**
     * The private gallery is a browser feature, so the browser may upload
     * into it - as long as it is unlocked.
     */
    protected function allowsPrivateUploads(): bool
    {
        return true;
    }

    /**
     * The private gallery is locked until its password was entered for the
     * current session.
     */
    protected function guardPrivateGallery(Request $request): void
    {
        $user = $request->user();

        if (! $user->hasPrivatePassword() || ! PrivateGallery::isUnlocked($request)) {
            abort(403, 'The private gallery is locked.');
        }
    }
}
