<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /**
     * Stream the user's profile picture through the app. Avatars are stored on
     * the photo disk via the Storage facade (like photo originals and
     * thumbnails), so they keep working on a serverless platform without
     * relying on the public/storage symlink surviving the build.
     */
    public function show(Request $request, User $user): StreamedResponse
    {
        if ($user->id !== $request->user()->id) {
            abort(403, 'You do not own this avatar.');
        }

        $disk = config('photobackup.photo_disk');

        if (! filled($user->avatar_path) || ! Storage::disk($disk)->exists($user->avatar_path)) {
            abort(404, 'Avatar file not found on disk.');
        }

        return Storage::disk($disk)->response(
            $user->avatar_path,
            basename($user->avatar_path),
            ['Content-Type' => 'image/png'],
        );
    }
}
