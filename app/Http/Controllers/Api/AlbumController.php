<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use App\Models\Album;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    /**
     * List the user's albums with photo counts.
     */
    public function index(Request $request): JsonResponse
    {
        $albums = $request->user()->albums()
            ->withCount('photos')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $albums->map(fn (Album $album) => $this->albumPayload($album)),
        ]);
    }

    /**
     * Create a new album.
     */
    public function store(CreateAlbumRequest $request): JsonResponse
    {
        $name = trim($request->name);

        $album = Album::firstOrCreate(
            ['user_id' => $request->user()->id, 'name' => $name],
            ['user_id' => $request->user()->id, 'name' => $name],
        );

        return response()->json([
            'message' => 'Album "'.$album->name.'" created.',
            'data' => $this->albumPayload($album),
        ], 201);
    }

    /**
     * Rename an album.
     */
    public function update(UpdateAlbumRequest $request, Album $album): JsonResponse
    {
        $this->authorizeAlbum($request, $album);

        $album->name = trim($request->name);
        $album->save();

        return response()->json([
            'message' => 'Album renamed to "'.$album->name.'".',
            'data' => $this->albumPayload($album),
        ]);
    }

    /**
     * Delete an album. Its photos remain (they are unassigned, not deleted).
     */
    public function destroy(Request $request, Album $album): JsonResponse
    {
        $this->authorizeAlbum($request, $album);

        $name = $album->name;
        $album->delete();

        return response()->json([
            'message' => 'Album "'.$name.'" deleted. Its photos were kept in your library.',
        ]);
    }

    /**
     * Add a photo to an album.
     */
    public function addPhoto(Request $request, Album $album, Photo $photo): JsonResponse
    {
        $this->authorizeAlbum($request, $album);
        $this->authorizePhoto($request, $photo);

        if ($album->photos()->where('photos.id', $photo->id)->exists()) {
            return response()->json(['message' => 'Photo is already in this album.'], 200);
        }

        $album->photos()->attach($photo);

        return response()->json(['message' => 'Photo added to "'.$album->name.'".']);
    }

    /**
     * Remove a photo from an album (the photo itself is not deleted).
     */
    public function removePhoto(Request $request, Album $album, Photo $photo): JsonResponse
    {
        $this->authorizeAlbum($request, $album);
        $this->authorizePhoto($request, $photo);

        $album->photos()->detach($photo);

        return response()->json(['message' => 'Photo removed from "'.$album->name.'".']);
    }

    /**
     * Serialize an album for JSON responses.
     */
    protected function albumPayload(Album $album): array
    {
        return [
            'id' => $album->id,
            'name' => $album->name,
            'photo_count' => $album->photos_count ?? $album->photoCount(),
            'created_at' => $album->created_at?->toISOString(),
        ];
    }

    /**
     * Ensure the authenticated user owns the album.
     */
    protected function authorizeAlbum(Request $request, Album $album): void
    {
        if ($album->user_id !== $request->user()->id) {
            abort(403, 'You do not own this album.');
        }
    }

    /**
     * Ensure the authenticated user owns the photo.
     */
    protected function authorizePhoto(Request $request, Photo $photo): void
    {
        if ($photo->user_id !== $request->user()->id) {
            abort(403, 'You do not own this photo.');
        }
    }
}
