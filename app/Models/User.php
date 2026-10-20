<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'private_password', 'storage_limit_mb', 'avatar_path', 'agreed_to_terms_at', 'provider', 'provider_id'])]
#[Hidden(['password', 'private_password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Default attribute values so a freshly created user always knows its
     * storage allowance, even before the row is read back from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'storage_limit_mb' => 5120,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'private_password' => 'hashed',
            'storage_limit_mb' => 'integer',
            'agreed_to_terms_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
        ];
    }

    /**
     * Public URL for the profile picture, or null when the user has not
     * uploaded one (the UI falls back to a generic placeholder icon).
     *
     * The image is streamed by the app through a controller route backed by
     * the Storage facade, so it does not depend on the public/storage symlink
     * that a shared hosting / buildpack setup may not create.
     */
    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar_path)) {
            return null;
        }

        return route('avatar', ['user' => $this->id], absolute: true);
    }

    /**
     * Up to two uppercase initials derived from the user's name.
     *
     * Uses the first letter of the name and the first letter of the surname,
     * skipping middle initials (a lone letter such as "M.") and name
     * extensions (Jr., Sr., II, III, IV, V). "Raul M. Colot Jr" -> "RC".
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '';
        }

        $suffixes = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v'];
        $withoutSuffixes = array_values(array_filter(
            $parts,
            fn (string $part) => ! in_array(mb_strtolower($part), $suffixes, true),
        ));

        $withoutInitials = array_values(array_filter(
            $withoutSuffixes,
            fn (string $part) => ! preg_match('/^[\p{L}]\.?$/u', $part),
        ));

        $significant = $withoutInitials !== [] ? $withoutInitials : $withoutSuffixes;

        if ($significant === []) {
            $significant = $parts;
        }

        $initials = mb_substr($significant[0], 0, 1);

        if (count($significant) > 1) {
            $initials .= mb_substr($significant[count($significant) - 1], 0, 1);
        }

        return mb_strtoupper($initials);
    }

    /**
     * Whether the user has an open account-deletion request. Requests are
     * reviewed manually, so the account is never removed automatically.
     */
    public function hasRequestedDeletion(): bool
    {
        return ! is_null($this->deletion_requested_at);
    }

    /**
     * File an account-deletion request (idempotent).
     */
    public function requestDeletion(): void
    {
        if (! $this->hasRequestedDeletion()) {
            $this->deletion_requested_at = now();
            $this->save();
        }
    }

    /**
     * Withdraw an account-deletion request.
     */
    public function cancelDeletion(): void
    {
        if ($this->hasRequestedDeletion()) {
            $this->deletion_requested_at = null;
            $this->save();
        }
    }

    /**
     * Photos owned by this user (including trashed ones).
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Photos in the user's password-protected private gallery.
     */
    public function privatePhotos(): HasMany
    {
        return $this->hasMany(Photo::class)->where('is_private', true);
    }

    /**
     * Albums owned by this user.
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /**
     * Whether the user has set up a private gallery password. Until one is
     * chosen, the private gallery cannot be used.
     */
    public function hasPrivatePassword(): bool
    {
        return filled($this->private_password);
    }

    /**
     * Total bytes currently used by this user's photos.
     */
    public function storageUsedBytes(): int
    {
        return (int) $this->photos()->sum('size_bytes');
    }

    /**
     * Bytes used by photos in the normal (non-private) library. Private photos
     * are kept out of the library storage totals.
     */
    public function libraryStorageUsedBytes(): int
    {
        return (int) $this->photos()->where('is_private', false)->sum('size_bytes');
    }

    /**
     * Percentage (0-100) of the storage allowance used by the non-private
     * library.
     */
    public function libraryStorageUsedPercent(): float
    {
        $limit = $this->storageLimitBytes();

        return $limit > 0 ? round($this->libraryStorageUsedBytes() / $limit * 100, 1) : 0.0;
    }

    /**
     * Total storage allowance in bytes.
     */
    public function storageLimitBytes(): int
    {
        return (int) $this->storage_limit_mb * 1024 * 1024;
    }

    /**
     * Percentage (0-100) of storage already used.
     */
    public function storageUsedPercent(): float
    {
        $limit = $this->storageLimitBytes();

        return $limit > 0 ? round($this->storageUsedBytes() / $limit * 100, 1) : 0.0;
    }

    /**
     * Whether the user has room for an additional file of the given size.
     */
    public function hasStorageFor(int $bytes): bool
    {
        return $this->storageUsedBytes() + $bytes <= $this->storageLimitBytes();
    }
}
