<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesPhotoUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePhotoRequest;
use App\Services\PhotoStorageService;
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
            'usedBytes' => $user->storageUsedBytes(),
            'limitBytes' => $user->storageLimitBytes(),
            'usedPercent' => $user->storageUsedPercent(),
            'trashedCount' => $user->photos()->onlyTrashed()->count(),
            'unbackedUpCount' => $user->photos()->whereNull('remote_path')->count(),
            'request' => $request,
        ]);
    }

    /**
     * All photos currently sitting in the trash.
     */
    public function trash(Request $request): View
    {
        $user = $request->user();

        return view('photos.trash', [
            'user' => $user,
            'photos' => $user->photos()->onlyTrashed()->orderByDesc('deleted_at')->paginate(24),
            'retentionDays' => (int) config('photobackup.trash_retention_days', 30),
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
        $user = $request->user();

        return view('settings', [
            'user' => $user,
            'activeTab' => $request->query('tab', 'profile'),
            'retentionDays' => (int) config('photobackup.trash_retention_days', 30),
            'supportEmail' => config('photobackup.support_email', 'support@example.com'),
            'photoCount' => $user->photos()->count(),
            'albumCount' => $user->albums()->count(),
            'usedBytes' => $user->storageUsedBytes(),
            'limitBytes' => $user->storageLimitBytes(),
            'usedPercent' => $user->storageUsedPercent(),
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
     * Permanently delete the account and all associated photos.
     */
    public function deleteAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => 'Please type your email address to confirm.',
            'email.email' => 'Please type a valid email address.',
        ]);

        $user = $request->user();

        if (strtolower($user->email) !== strtolower(trim($request->email))) {
            throw ValidationException::withMessages([
                'email' => 'The email you typed does not match your account.',
            ]);
        }

        $photos = $user->photos()->withTrashed()->get();
        foreach ($photos as $photo) {
            $this->photos->purge($photo);
        }

        $user->albums()->delete();
        $user->tokens()->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your account and all of your photos have been permanently deleted.');
    }
}
