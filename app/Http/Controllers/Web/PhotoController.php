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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoController extends Controller
{
    public function __construct(
        protected PhotoStorageService $photos,
    ) {}

    /**
     * Show a photo on an enlarged preview page with zoom, details and actions.
     */
    public function show(Request $request, Photo $photo): View
    {
        $this->authorizePhoto($request, $photo);

        return view('photos.show', [
            'user' => $request->user(),
            'photo' => $photo,
            'albums' => $request->user()->albums()->orderBy('name')->get(),
            'photoAlbums' => $photo->albums()->pluck('albums.id'),
        ]);
    }

    /**
     * Stream the binary image file to the browser.
     */
    public function raw(Request $request, Photo $photo): StreamedResponse
    {
        $this->authorizePhoto($request, $photo);

        if (! Storage::disk('public')->exists($photo->file_path)) {
            abort(404, 'Photo file not found on disk.');
        }

        return Storage::disk('public')->response(
            $photo->file_path,
            basename($photo->original_filename),
            ['Content-Type' => $photo->mime_type ?? 'application/octet-stream'],
            'inline',
        );
    }

    /**
     * Stream a small generated thumbnail for the photo, so the grid loads
     * lightweight images and the full-size file is only fetched when a photo is
     * actually opened. Gracefully falls back to the original image when no
     * thumbnail exists yet (e.g. pre-feature uploads or generation failures).
     */
    public function thumbnail(Request $request, Photo $photo): StreamedResponse
    {
        $this->authorizePhoto($request, $photo);

        if (filled($photo->thumbnail_path) && Storage::disk('public')->exists($photo->thumbnail_path)) {
            return Storage::disk('public')->response(
                $photo->thumbnail_path,
                basename($photo->thumbnail_path),
                ['Content-Type' => 'image/jpeg'],
                'inline',
            );
        }

        return $this->raw($request, $photo);
    }

    /**
     * Download a single photo.
     */
    public function download(Request $request, Photo $photo): BinaryFileResponse|RedirectResponse
    {
        $this->authorizePhoto($request, $photo);

        if (! Storage::disk('public')->exists($photo->file_path)) {
            return back()->withErrors(['download' => 'The photo file could not be found on disk.']);
        }

        return Storage::disk('public')->download(
            $photo->file_path,
            basename($photo->original_filename),
        );
    }

    /**
     * Download several selected photos as a single ZIP archive.
     *
     * Only the owner's own photos are ever included, and the selection is
     * capped so an oversized request gets a clear message instead of a timeout.
     */
    public function downloadSelected(Request $request): BinaryFileResponse|RedirectResponse
    {
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $maxPhotos = 200;

        if ($ids->isEmpty()) {
            return back()->withErrors(['download' => 'Select at least one photo to download.']);
        }

        if ($ids->count() > $maxPhotos) {
            return back()->withErrors(['download' => "Please select up to {$maxPhotos} photos at a time to download."]);
        }

        $photos = $request->user()->photos()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        if ($photos->isEmpty()) {
            return back()->withErrors(['download' => 'The selected photos could not be found.']);
        }

        $tmpPath = $this->photos->buildZip($photos, 'selected');

        if ($tmpPath === null) {
            return back()->withErrors(['download' => 'Could not create the ZIP archive.']);
        }

        return response()->download($tmpPath, 'selected-photos.zip')->deleteFileAfterSend(true);
    }

    /**
     * Update the alt text (and optional tags) for a photo.
     */
    public function updateDiskMeta(Request $request, Photo $photo): RedirectResponse
    {
        $this->authorizePhoto($request, $photo);

        $request->validate([
            'alt_text' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:255'],
        ], [
            'alt_text.max' => 'Alt text must be 500 characters or fewer.',
            'tags.max' => 'Tags must be 255 characters or fewer.',
        ]);

        $photo->alt_text = filled($request->alt_text) ? trim($request->alt_text) : null;
        $photo->tags = $request->filled('tags')
            ? $this->photos->normalizeTags($request->tags)
            : null;
        $photo->save();

        return back()->with('status', 'Photo details updated.');
    }

    /**
     * Move a photo to the trash (reversible).
     */
    public function trash(Request $request, Photo $photo): RedirectResponse|JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->photos->trash($photo);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Photo moved to trash. You can restore it within the retention window.']);
        }

        return redirect()->route('dashboard')->with(
            'status',
            'Photo moved to trash. You can restore it within the retention window.'
        );
    }

    /**
     * Restore a photo from the trash.
     */
    public function restore(Request $request, Photo $photo): RedirectResponse|JsonResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->photos->restore($photo);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Photo restored from trash.']);
        }

        $referer = $request->headers->get('referer');

        return ($referer !== null && str_contains((string) $referer, 'trash'))
            ? back()->with('status', 'Photo restored from trash.')
            : redirect()->route('dashboard')->with('status', 'Photo restored from trash.');
    }

    /**
     * Permanently delete a single trashed photo. The confirmation is handled by
     * a client-side Yes/No dialog; there is no typed confirmation word.
     */
    public function purge(Request $request, Photo $photo): RedirectResponse
    {
        $this->authorizePhoto($request, $photo);

        $this->photos->purge($photo);

        return redirect()->route('trash')->with('status', 'Photo permanently deleted.');
    }

    /**
     * Ensure the authenticated user owns the photo.
     */
    protected function authorizePhoto(Request $request, Photo $photo): void
    {
        if ($photo->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
