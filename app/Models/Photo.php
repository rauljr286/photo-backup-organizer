<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'file_path',
    'thumbnail_path',
    'original_filename',
    'file_hash',
    'alt_text',
    'tags',
    'taken_at',
    'size_bytes',
    'mime_type',
    'remote_path',
    'backed_up_at',
])]
#[Hidden([])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'backed_up_at' => 'datetime',
            'size_bytes' => 'integer',
            'tags' => 'array',
        ];
    }

    /**
     * The user who owns this photo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Albums this photo belongs to.
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)->withTimestamps();
    }

    /**
     * Whether this photo has been backed up to external/cloud storage.
     */
    public function isBackedUp(): bool
    {
        return filled($this->remote_path);
    }

    /**
     * Public URL that serves the image file through Laravel.
     */
    public function url(): string
    {
        return route('photos.raw', ['photo' => $this->id], absolute: true);
    }

    /**
     * Public URL that serves a generated thumbnail.
     *
     * Graceful degradation: when no thumbnail exists yet (e.g. uploaded before
     * the feature landed, or generation failed) the grid falls back to the
     * full-size image so thumbnails are an optimisation, never a blocker.
     */
    public function thumbnailUrl(): string
    {
        return filled($this->thumbnail_path)
            ? route('photos.thumbnail', ['photo' => $this->id], absolute: true)
            : $this->url();
    }

    /**
     * Default descriptive alt text derived from metadata.
     */
    public function defaultAltText(): string
    {
        $date = $this->taken_at?->format('F Y');
        $fallback = "Photo \"{$this->original_filename}\"";

        return $date
            ? "Photo taken {$date}, {$this->original_filename}"
            : "{$fallback}, uploaded {$this->created_at?->format('F j, Y')}";
    }

    /**
     * The alt text that should actually be rendered for screen readers.
     */
    public function description(): string
    {
        return filled($this->alt_text)
            ? $this->alt_text
            : $this->defaultAltText();
    }
}
