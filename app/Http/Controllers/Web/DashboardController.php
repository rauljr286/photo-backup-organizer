<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesPhotoUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePhotoRequest;
use App\Services\PhotoStorageService;
use App\Support\PrivateGallery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    use HandlesPhotoUploads;

    public function __construct(
        protected PhotoStorageService $photos,
    ) {}

    /**
     * Photo grid with search & filter controls, storage usage and upload zone.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'integer'],
            'tags' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $paginator = $this->photos->filteredPhotos($user, $filters, 24);

        return view('dashboard', [
            'user' => $user,
            'photos' => $paginator,
            'albums' => $user->albums()->withCount('photos')->orderBy('name')->get(),
            'allTags' => $this->photos->allTagsFor($user),
            'activeAlbum' => $filters['album'] ?? null,
            'activeTags' => collect(explode(',', (string) ($filters['tags'] ?? '')))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values(),
            'usedBytes' => $user->libraryStorageUsedBytes(),
            'limitBytes' => $user->storageLimitBytes(),
            'usedPercent' => $user->libraryStorageUsedPercent(),
            'activeCount' => $user->photos()->notPrivate()->whereNull('archived_at')->count(),
            'archivedCount' => $user->photos()->notPrivate()->archived()->count(),
            'unbackedUpCount' => $user->photos()->notPrivate()->whereNull('remote_path')->count(),
            'request' => $request,
        ]);
    }

    /**
     * All photos currently sitting in the archive.
     */
    public function archive(Request $request): View
    {
        $user = $request->user();

        return view('photos.archive', [
            'user' => $user,
            'photos' => $user->photos()->notPrivate()->archived()->orderByDesc('archived_at')->paginate(24),
        ]);
    }

    /**
     * Return photos newer than the given id that match the current filters.
     * The dashboard polls this so photos uploaded from another device appear
     * on screen without a manual refresh.
     */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'integer'],
            'tags' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $afterId = (int) ($filters['after'] ?? 0);
        $photos = $this->photos->latestPhotos($user, $filters, $afterId);

        return response()->json([
            'photos' => $photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->url(),
                'thumbnail_url' => $photo->thumbnailUrl(),
                'description' => $photo->description(),
                'size_bytes' => $photo->size_bytes,
                'taken_at' => $photo->taken_at?->toISOString(),
                'backed_up' => $photo->isBackedUp(),
            ])->values(),
            'latest_id' => $photos->max('id') ?? $afterId,
        ]);
    }

    /**
     * Upload one or more photos from the dashboard (returns JSON so the
     * front-end can show per-file progress and failure reasons).
     */
    public function upload(StorePhotoRequest $request): JsonResponse
    {
        return $this->uploadPhotos($request);
    }

    /**
     * Push photos without a cloud backup to the configured cloud destination.
     */
    public function backupNow(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pending = $user->photos()->whereNull('remote_path')->get();

        $done = 0;
        $failed = 0;

        foreach ($pending as $photo) {
            try {
                $this->photos->backupToCloud($photo);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
            }
        }

        if ($done === 0 && $failed === 0) {
            return back()->with('status', 'All of your photos are already backed up.');
        }

        $message = "Backup complete: {$done} photo(s) backed up.";

        if ($failed > 0) {
            $message .= " {$failed} photo(s) could not be backed up and were left untouched.";
        }

        return back()->with('status', $message);
    }

    /**
     * Account settings, terms of service and privacy policy.
     */
    public function settings(Request $request): View
    {
        return $this->renderSettings($request, 'profile');
    }

    /**
     * Preferences & security tab.
     */
    public function settingsSecurity(Request $request): View
    {
        return $this->renderSettings($request, 'security');
    }

    /**
     * Privacy & data tab.
     */
    public function settingsPrivacy(Request $request): View
    {
        return $this->renderSettings($request, 'privacy');
    }

    /**
     * Private gallery tab.
     */
    public function settingsPrivate(Request $request): View
    {
        return $this->renderSettings($request, 'private');
    }

    /**
     * Build the account area with the requested tab highlighted.
     */
    protected function renderSettings(Request $request, string $fallbackTab): View
    {
        $user = $request->user();
        $tab = $request->query('tab', $fallbackTab);

        if (! in_array($tab, ['profile', 'security', 'privacy', 'private'], true)) {
            $tab = $fallbackTab;
        }

        return view('settings', [
            'user' => $user,
            'activeTab' => $tab,
            'supportEmail' => config('photobackup.support_email', 'support@example.com'),
            'photoCount' => $user->photos()->notPrivate()->count(),
            'activeCount' => $user->photos()->notPrivate()->whereNull('archived_at')->count(),
            'archivedCount' => $user->photos()->notPrivate()->archived()->count(),
            'albumCount' => $user->albums()->count(),
            'usedBytes' => $user->libraryStorageUsedBytes(),
            'limitBytes' => $user->storageLimitBytes(),
            'usedPercent' => $user->libraryStorageUsedPercent(),
            'privateConfigured' => $user->hasPrivatePassword(),
            'privateUnlocked' => PrivateGallery::isUnlocked($request),
            'privatePhotoCount' => $user->privatePhotos()->count(),
        ]);
    }

    /**
     * Update the signed-in user's display name. Returns JSON when the request
     * is made by the front-end (so the settings page can update in place) and
     * falls back to a redirect for plain form submissions.
     */
    public function updateName(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Please enter your name.',
            'name.max' => 'Your name cannot be longer than 255 characters.',
        ]);

        $user = $request->user();
        $user->name = trim($validated['name']);
        $user->save();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Your name has been updated.',
                'user' => ['name' => $user->name],
            ]);
        }

        return back()->with('status', 'Your name has been updated.');
    }

    /**
     * Upload a new profile picture. The image is centre-cropped to a square and
     * resized to a 256px avatar stored on the public disk.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'avatar.required' => 'Please choose a profile picture.',
            'avatar.image' => 'The file must be an image.',
            'avatar.mimes' => 'Profile pictures must be a JPG, PNG or WebP image.',
            'avatar.max' => 'The profile picture must be 5MB or smaller.',
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        if (filled($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = $this->storeAvatar($file, $user->id);
        $user->save();

        return back()->with('status', 'Your profile picture has been updated.');
    }

    /**
     * Centre-crop the uploaded image to a square and resize it to a 256px PNG.
     */
    protected function storeAvatar(UploadedFile $file, int $userId): string
    {
        $source = match ($file->getMimeType()) {
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => @imagecreatefromjpeg($file->getRealPath()),
        };

        if ($source === false) {
            throw ValidationException::withMessages([
                'avatar' => 'The selected file could not be read as an image. Please try another file.',
            ]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $size = min($width, $height);
        $sx = (int) (($width - $size) / 2);
        $sy = (int) (($height - $size) / 2);

        $crop = imagecreatetruecolor($size, $size);
        imagecopy($crop, $source, 0, 0, $sx, $sy, $size, $size);

        $avatar = imagecreatetruecolor(256, 256);
        imagealphablending($avatar, false);
        imagesavealpha($avatar, true);
        imagefill($avatar, 0, 0, imagecolorallocate($avatar, 255, 255, 255));
        imagecopyresampled($avatar, $crop, 0, 0, 0, 0, 256, 256, $size, $size);

        ob_start();
        imagepng($avatar);
        $data = ob_get_clean();

        imagedestroy($source);
        imagedestroy($crop);
        imagedestroy($avatar);

        $path = 'avatars/'.$userId.'-'.Str::random(20).'.png';
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    /**
     * Change the signed-in user's password.
     */
    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'password.required' => 'Please choose a new password.',
            'password.min' => 'Your new password must be at least 8 characters long.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $user->password = $request->password;
        $user->save();

        return back()->with('status', 'Your password has been updated.');
    }

    /**
     * File an account-deletion request. Nothing is deleted here: the request is
     * recorded for manual review and the user keeps full access until then.
     */
    public function requestAccountDeletion(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Please confirm you understand the request is reviewed manually.',
        ]);

        $user = $request->user();
        $user->requestDeletion();

        return back()->with('status', 'Your account deletion request has been received. Nothing has been deleted yet — our team will review it and contact you by email.');
    }

    /**
     * Withdraw an open account-deletion request.
     */
    public function cancelAccountDeletion(Request $request): RedirectResponse
    {
        $request->user()->cancelDeletion();

        return back()->with('status', 'Your account deletion request has been cancelled.');
    }

    /**
     * Sign the user out of every other device by re-hashing the password so
     * any other session's authentication is invalidated.
     */
    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => 'Please enter your current password.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        Auth::logoutOtherDevices($request->current_password);

        return back()->with('status', 'You have been signed out of your other devices.');
    }
}
