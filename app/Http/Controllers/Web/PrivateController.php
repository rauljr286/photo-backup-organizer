<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesPhotoUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePhotoRequest;
use App\Support\PrivateGallery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The password-protected private gallery. Private photos live in their own
 * space with a separate password, are hidden from the normal library, and the
 * area stays locked until the password is entered for the current session.
 */
class PrivateController extends Controller
{
    use HandlesPhotoUploads;

    /**
     * Show the private gallery: a set-up screen when no private password
     * exists yet, an unlock screen while locked, or the photo grid once
     * unlocked.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $mode = match (true) {
            ! $user->hasPrivatePassword() => 'setup',
            ! PrivateGallery::isUnlocked($request) => 'locked',
            default => 'gallery',
        };

        return view('private.index', [
            'user' => $user,
            'mode' => $mode,
            'photos' => $mode === 'gallery'
                ? $user->privatePhotos()->orderByDesc('created_at')->orderByDesc('id')->paginate(24)
                : null,
        ]);
    }

    /**
     * Set up the private password, or change it when one already exists (in
     * which case the current private password is required). A successful save
     * unlocks the gallery for this session.
     */
    public function storePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'private_password' => ['required', 'string', 'min:4', 'max:255', 'confirmed'],
        ];

        if ($user->hasPrivatePassword()) {
            $rules['current_private_password'] = ['required', 'string'];
        }

        $request->validate($rules, [
            'private_password.required' => 'Please choose a private password.',
            'private_password.min' => 'Your private password must be at least 4 characters.',
            'private_password.confirmed' => 'The private password confirmation does not match.',
            'current_private_password.required' => 'Please enter your current private password.',
        ]);

        if ($user->hasPrivatePassword()
            && ! Hash::check((string) $request->input('current_private_password'), (string) $user->private_password)) {
            throw ValidationException::withMessages([
                'current_private_password' => 'Your current private password is incorrect.',
            ]);
        }

        $user->private_password = (string) $request->input('private_password');
        $user->save();

        PrivateGallery::unlock($request);

        return back()->with('status', 'Private gallery password saved. Your private photos are unlocked.');
    }

    /**
     * Unlock the private gallery for the current session with its password.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $request->validate([
            'private_password' => ['required', 'string'],
        ], [
            'private_password.required' => 'Please enter your private password.',
        ]);

        $user = $request->user();

        if (! $user->hasPrivatePassword()
            || ! Hash::check((string) $request->input('private_password'), (string) $user->private_password)) {
            throw ValidationException::withMessages([
                'private_password' => 'That private password is incorrect.',
            ]);
        }

        if ($request->input('context') === 'settings') {
            $token = PrivateGallery::unlockSettings($request);

            return redirect()
                ->route('profile.private', ['private_unlock' => $token])
                ->with('status', 'Private settings unlocked.');
        }

        PrivateGallery::unlock($request);

        // Land straight on the gallery so the unlocked photos are shown, no
        // matter which page the popup was opened from.
        return redirect()->route('private')->with('status', 'Private gallery unlocked.');
    }

    /**
     * Lock the private gallery again for the current session.
     */
    public function lock(Request $request): RedirectResponse
    {
        PrivateGallery::lock($request);

        return back()->with('status', 'Private gallery locked.');
    }

    /**
     * Reset the private password using the account password. This is the
     * recovery path for a forgotten private password; it re-locks the gallery.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'private_password' => ['required', 'string', 'min:4', 'max:255', 'confirmed'],
        ], [
            'current_password.required' => 'Please enter your account password.',
            'private_password.required' => 'Please choose a new private password.',
            'private_password.min' => 'Your private password must be at least 4 characters.',
            'private_password.confirmed' => 'The private password confirmation does not match.',
        ]);

        $user = $request->user();

        if (! Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your account password is incorrect.',
            ]);
        }

        $user->private_password = (string) $request->input('private_password');
        $user->save();

        PrivateGallery::lock($request);

        return back()->with('status', 'Private gallery password reset. Use your new password to unlock it.');
    }

    /**
     * Upload photos straight into the private gallery.
     */
    public function upload(StorePhotoRequest $request): JsonResponse
    {
        if (! $request->user()->hasPrivatePassword() || ! PrivateGallery::isUnlocked($request)) {
            abort(403, 'The private gallery is locked.');
        }

        return $this->handlePhotoUpload($request, true);
    }

    /**
     * Return one page of private photos as JSON so the grid can keep loading
     * more photos while the user scrolls instead of showing numbered pages.
     */
    public function page(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPrivatePassword() || ! PrivateGallery::isUnlocked($request)) {
            return response()->json([
                'photos' => [],
                'has_more' => false,
                'next_page' => null,
                'total' => 0,
            ], 403);
        }

        $paginator = $user->privatePhotos()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(DashboardController::PHOTOS_PER_PAGE);

        return response()->json([
            'photos' => $paginator->getCollection()
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->url(),
                    'thumbnail_url' => $photo->thumbnailUrl(),
                    'description' => $photo->description(),
                    'size_bytes' => $photo->size_bytes,
                    'taken_at' => $photo->taken_at?->toISOString(),
                    'backed_up' => $photo->isBackedUp(),
                ])
                ->values(),
            'has_more' => $paginator->hasMorePages(),
            'next_page' => $paginator->currentPage() + 1,
            'total' => $paginator->total(),
        ]);
    }
}
