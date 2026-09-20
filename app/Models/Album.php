<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'name'])]
class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory;

    /**
     * The user who owns this album.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Photos contained in this album.
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class)->withTimestamps();
    }

    /**
     * Number of photos in the album (excluding trashed photos).
     */
    public function photoCount(): int
    {
        return $this->photos()->count();
    }
}
