<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    "user_id",
    "file_path",
    "thumbnail_path",
    "original_filename",
    "file_hash",
    "alt_text",
    "tags",
    "taken_at",
    "size_bytes",
    "mime_type",
    "remote_path",
    "backed_up_at",
    "archived_at",
])]
#[Hidden([])]
class Photo extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            "taken_at" => "datetime",
            "backed_up_at" => "datetime",
            "archived_at" => "datetime",
            "deleted_at" => "datetime",
            "size_bytes" => "integer",
            "tags" => "array",
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)->withTimestamps();
    }

    public function isBackedUp(): bool
    {
        return filled($this->remote_path);
    }

    public function url(): string
    {
        return route("photos.raw", ["photo" => $this->id], absolute: true);
    }

    public function thumbnailUrl(): string
    {
        return filled($this->thumbnail_path)
            ? route("photos.thumbnail", ["photo" => $this->id], absolute: true)
            : $this->url();
    }

    public function defaultAltText(): string
    {
        $date = $this->taken_at?->format("F Y");
        $fallback = "Photo \"{$this->original_filename}\"";

        return $date
            ? "Photo taken {$date}, {$this->original_filename}"
            : "{$fallback}, uploaded {$this->created_at?->format("F j, Y")}";
    }

    public function description(): string
    {
        return filled($this->alt_text)
            ? $this->alt_text
            : $this->defaultAltText();
    }

    public function archive(): void
    {
        $this->archived_at = now();
        $this->save();
    }

    public function unarchive(): void
    {
        $this->archived_at = null;
        $this->save();
    }

    public function isArchived(): bool
    {
        return ! is_null($this->archived_at);
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull("archived_at");
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull("archived_at");
    }
}