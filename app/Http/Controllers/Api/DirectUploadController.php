<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesDirectUploads;
use App\Http\Controllers\Controller;

/**
 * The direct (presigned) upload for API clients: the photo is sent straight
 * to the bucket, so it never travels through the application server.
 */
class DirectUploadController extends Controller
{
    use HandlesDirectUploads;
}
