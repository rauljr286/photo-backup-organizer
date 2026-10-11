<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Services\PhotoStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController extends Controller
{
    public function __construct(
        protected PhotoStorageService $photos,
    ) {}

    public function show(Request $request, Photo $photo): View
    {
        $this->authorizePhoto($request, $photo);

        $siblings = $request->user()->photos()
            ->when(! $photo->isArchived(), fn ($query) => $query->whereNull('archived_at'))
            ->orderByRaw('taken_at IS NULL, taken_at DESC, id DESC')
            ->pluck('id')
            ->all();

        $index = array_search($photo->id, $siblings, true);

        return view('photos.show', [
            'user' => $request->user(),
            'photo' => $photo,
            'prevPhoto' => is_int($index) && isset($siblings[$index - 1])
                ? Photo::find($siblings[$index - 1])
                : null,
            'nextPhoto' => is_int($index) && isset($siblings[$index + 1])
                ? Photo::find($siblings[$index + 1])
                : null,
            'albums' => $request->user()->albums()->orderBy('name')->get(),
            'photoAlbums' => $photo->albums()->pluck('albums.id'),
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
            $photo->original_filename ?: basename($photo->file_path),
            [
                'Content-Type' => $photo->mime_type ?? 'application/octet-stream',
            ],
            $download ? 'attachment' : 'inline',
        );
    }

    /**
     * Stream the photo's thumbnail, falling back to the original file when a
     * thumbnail has not been generated yet.
     */
    public function thumbnail(Request $request, Photo $photo): StreamedResponse|BinaryFileResponse
    {
        $this->authorizePhoto($request, $photo);

        if (filled($photo->thumbnail_path) && Storage::disk('public')->exists($photo->thumbnail_path)) {
            return Storage::disk('public')->response(
                $photo->thumbnail_path,
                basename($photo->thumbnail_path),
                ['Content-Type' => 'image/jpeg'],
            );
        }

        return $this->raw($request, $photo);
    }

    /**
     * Download a single photo as an attachment.
     */
    public function download(Request $request, Photo $photo): StreamedResponse|BinaryFileResponse
    {
        $this->authorizePhoto($request, $photo);

        if (! Storage::disk('public')->exists($photo->file_path)) {
            abort(404, 'Photo file not found on disk.');
        }

        return Storage::disk('public')->response(
            $photo->file_path,
            $photo->original_filename ?: basename($photo->file_path),
            [
                'Content-Type' => $photo->mime_type ?? 'application/octet-stream',
            ],
            'attachment',
        );
    }

    /**
     * Bundle a set of the user's photos into a ZIP archive for download.
     */
    public function downloadSelected(Request $request): BinaryFileResponse|RedirectResponse
    {
        $ids = (array) $request->input('ids', []);

        if ($ids === []) {
            throw ValidationException::withMessages([
                'download' => 'Select at least one photo to download.',
            ]);
        }

        if (count($ids) > 200) {
            throw ValidationException::withMessages([
                'download' => 'You can download up to 200 photos at a time.',
            ]);
        }

        $photos = $request->user()->photos()->whereIn('photos.id', $ids)->get();

        $zip = $this->photos->buildZip($photos, 'photos');

        if ($zip === null) {
            throw ValidationException::withMessages([
                'download' => 'The selected photos could not be packed into a download.',
            ]);
        }

        return response()->download($zip, 'photos.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Update the photo's alt text and tags.
     */
    public function updateDiskMeta(Request $request, Photo $photo): RedirectResponse|JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $validated = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        $photo->alt_text = trim((string) ($validated['alt_text'] ?? '')) !== ''
            ? trim((string) $validated['alt_text'])
            : null;

        $photo->tags = collect(explode(',', (string) ($validated['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))
            ->filter(fn ($tag) => $tag !== '')
            ->unique()
            ->values()
            ->all();

        $photo->save();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Photo details updated.',
                'photo' => [
                    'alt_text' => $photo->alt_text,
                    'description' => $photo->description(),
                    'tags' => $photo->tags ?? [],
                ],
            ]);
        }

        return back()->with('status', 'Photo details updated.');
    }

    /**
     * Move a photo to the archive (reversible, never permanent for users).
     */
    public function archive(Request $request, Photo $photo): RedirectResponse|JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->photos->archive($photo);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Photo archived.',
                'archived_at' => $photo->fresh()->archived_at?->toISOString(),
            ]);
        }

        return back()->with('status', 'Photo archived.');
    }

    /**
     * Restore a photo from the archive back to the main photo library.
     */
    public function unarchive(Request $request, Photo $photo): RedirectResponse|JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->photos->unarchive($photo);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Photo restored from archive.',
            ]);
        }

        return redirect()->route('dashboard')->with('status', 'Photo restored from archive.');
    }

    /**
     * Apply the same description, tags and album to a set of selected photos.
     */
    public function updateBulkMeta(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'integer'],
        ], [
            'ids.required' => 'Select at least one photo.',
            'ids.max' => 'You can edit up to 200 photos at a time.',
        ]);

        $result = $this->photos->applyBulkUpdate(
            $request->user(),
            $validated['ids'],
            $validated['alt_text'] ?? null,
            $validated['tags'] ?? null,
            array_key_exists('album', $validated) ? $validated['album'] : null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Updated {$result['count']} photo(s).",
            ]);
        }

        return back()->with('status', "Updated {$result['count']} photo(s).");
    }

    /**
     * Rotate a photo 90 degrees in place and regenerate its thumbnail.
     */
    public function rotate(Request $request, Photo $photo): RedirectResponse|JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $request->validate([
            'direction' => ['required', 'in:left,right'],
        ]);

        if (! $this->photos->rotate($photo, $request->direction)) {
            throw ValidationException::withMessages([
                'rotate' => 'This photo could not be rotated. Only JPG, PNG and WebP images can be edited.',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Photo rotated.']);
        }

        return back()->with('status', 'Photo rotated.');
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
