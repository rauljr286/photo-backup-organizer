<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use App\Models\Album;
use App\Services\PhotoStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AlbumController extends Controller
{
    public function __construct(
        protected PhotoStorageService $photos,
    ) {}

    /**
     * List all albums with their photo counts.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('albums.index', [
            'user' => $user,
            'albums' => $user->albums()->withCount('photos')->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the photos inside a single album.
     */
    public function show(Request $request, Album $album): View
    {
        $this->authorizeAlbum($request, $album);
        $user = $request->user();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $filters['album'] = $album->id;

        $paginator = $this->photos->filteredPhotos($user, $filters, 24);

        return view('albums.show', [
            'album' => $album,
            'user' => $user,
            'photos' => $paginator,
            'albums' => $user->albums()->withCount('photos')->orderBy('name')->get(),
            'allTags' => $this->photos->allTagsFor($user),
            'activeTags' => collect(explode(',', (string) ($filters['tags'] ?? '')))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values(),
            'request' => $request,
        ]);
    }

    /**
     * Create a new album.
     */
    public function store(CreateAlbumRequest $request): RedirectResponse
    {
        Album::firstOrCreate(
            ['user_id' => $request->user()->id, 'name' => trim($request->name)],
            ['user_id' => $request->user()->id, 'name' => trim($request->name)],
        );

        return back()->with('status', 'Album created.');
    }

    /**
     * Rename an album.
     */
    public function update(UpdateAlbumRequest $request, Album $album): RedirectResponse
    {
        $this->authorizeAlbum($request, $album);

        $album->name = trim($request->name);
        $album->save();

        return back()->with('status', 'Album renamed.');
    }

    /**
     * Delete an album. Its photos remain in the library.
     */
    public function destroy(Request $request, Album $album): RedirectResponse
    {
        $this->authorizeAlbum($request, $album);

        $album->delete();

        return redirect()->route('albums.index')->with('status', 'Album deleted. Its photos were kept in your library.');
    }

    /**
     * Download every photo in the album as a ZIP archive.
     */
    public function download(Request $request, Album $album): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeAlbum($request, $album);

        $photos = $album->photos()->get();
        $tmpPath = $this->photos->buildZip($photos, 'album');
        $safeName = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $album->name) ?: 'album';

        if ($tmpPath === null) {
            return back()->withErrors(['download' => 'Could not create the ZIP archive.']);
        }

        return response()->download($tmpPath, $safeName.'.zip')->deleteFileAfterSend(true);
    }

    /**
     * Attach a photo to an album (used from the photo detail page).
     */
    public function addPhoto(Request $request, Album $album): RedirectResponse
    {
        $this->authorizeAlbum($request, $album);

        $request->validate(['photo_id' => ['required', 'integer', 'exists:photos,id']]);
        $photo = $request->user()->photos()->findOrFail($request->photo_id);

        $album->photos()->syncWithoutDetaching($photo);

        return back()->with('status', 'Photo added to "'.$album->name.'".');
    }

    /**
     * Remove a photo from an album.
     */
    public function removePhoto(Request $request, Album $album): RedirectResponse
    {
        $this->authorizeAlbum($request, $album);

        $request->validate(['photo_id' => ['required', 'integer', 'exists:photos,id']]);
        $photo = $request->user()->photos()->findOrFail($request->photo_id);

        $album->photos()->detach($photo);

        return back()->with('status', 'Photo removed from "'.$album->name.'".');
    }

    /**
     * Ensure the authenticated user owns the album.
     */
    protected function authorizeAlbum(Request $request, Album $album): void
    {
        if ($album->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
