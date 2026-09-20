<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesPhotoUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePhotoRequest;
use App\Models\Photo;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController extends Controller
{
    use HandlesPhotoUploads;

    public function __construct(
        protected PhotoStorageService $photos,
    ) {}

    /**
     * List the user's photos, optional filters: album, date (from/to), tags, search.
     */
    public function index(Request $request): JsonResponse
    {
        $safearray = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'integer'],
            'tags' => ['nullable'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'trashed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filtered = $this->photos->filteredPhotos(
            $request->user(),
            $safearray,
            (int) ($safearray['per_page'] ?? 30),
        );

        return response()->json([
            'data' => $filtered->map(fn (Photo $photo) => $this->photoPayload($photo)),
            'pagination' => [
                'current_page' => $filtered->currentPage(),
                'last_page' => $filtered->lastPage(),
                'total' => $filtered->total(),
            ],
        ]);
    }

    /**
     * Upload one or more photos. Each file is processed independently and the
     * response reports per-file success/failure with the actual reason.
     */
    public function store(StorePhotoRequest $request): JsonResponse
    {
        return $this->uploadPhotos($request);
    }

    /**
     * Details of a single photo.
     */
    public function show(Request $request, Photo $photo): JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        return response()->json(['data' => $this->photoPayload($photo)]);
    }

    /**
     * Move a photo to the trash (soft delete, reversible).
     */
    public function destroy(Request $request, Photo $photo): JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->photos->trash($photo);

        return response()->json([
            'message' => 'Photo moved to trash. You can restore it within the retention window.',
            'trashed_at' => $photo->deleted_at?->toISOString(),
        ]);
    }

    /**
     * Restore a photo from the trash.
     */
    public function restore(Request $request, Photo $photo): JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->photos->restore($photo);

        return response()->json([
            'message' => 'Photo restored from trash.',
        ]);
    }

    /**
     * Stream the photo's binary file to the client.
     */
    public function raw(Request $request, Photo $photo): StreamedResponse|BinaryFileResponse
    {
        $this->authorizePhoto($request, $photo);

        if (! Storage::disk('public')->exists($photo->file_path)) {
            abort(404, 'Photo file not found on disk.');
        }

        $download = $request->query('download') === '1';

        return Storage::disk('public')->response(
            $photo->file_path,
            basename($photo->original_filename),
            [
                'Content-Type' => $photo->mime_type ?? 'application/octet-stream',
            ],
            $download ? 'attachment' : 'inline',
        );
    }

    /**
     * Serialize a photo for JSON responses.
     */
    protected function photoPayload(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'original_filename' => $photo->original_filename,
            'alt_text' => $photo->alt_text,
            'description' => $photo->description(),
            'tags' => $photo->tags ?? [],
            'taken_at' => $photo->taken_at?->toISOString(),
            'uploaded_at' => $photo->created_at?->toISOString(),
            'deleted_at' => $photo->deleted_at?->toISOString(),
            'size_bytes' => $photo->size_bytes,
            'mime_type' => $photo->mime_type,
            'backed_up' => $photo->isBackedUp(),
            'url' => $photo->url(),
            'albums' => $photo->albums()->pluck('name', 'albums.id'),
        ];
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
