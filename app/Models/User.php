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
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'storage_limit_mb', 'avatar_path', 'agreed_to_terms_at'])]
#[Hidden(['password', 'remember_token'])]
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
            'storage_limit_mb' => 'integer',
            'agreed_to_terms_at' => 'datetime',
        ];
    }

    /**
     * Public URL for the profile picture, or null when the user has not
     * uploaded one (the UI falls back to a generic placeholder icon).
     *
     * The URL is resolved against the current request host so it stays valid
     * regardless of APP_URL (mirrors how Photo::url() builds photo URLs).
     */
    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar_path)) {
            return null;
        }

        return URL::asset('storage/'.$this->avatar_path);
    }

    /**
     * Photos owned by this user (including trashed ones).
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Albums owned by this user.
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /**
     * Total bytes currently used by this user's photos.
     */
    public function storageUsedBytes(): int
    {
        return (int) $this->photos()->sum('size_bytes');
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
