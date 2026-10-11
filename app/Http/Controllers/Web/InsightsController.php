<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InsightsController extends Controller
{
    /**
     * Aggregate the user's library: storage by year, busiest months, top tags
     * and the largest photos.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        $photos = $user->photos()
            ->select(['id', 'original_filename', 'file_path', 'thumbnail_path', 'mime_type', 'taken_at', 'created_at', 'file_hash', 'size_bytes', 'archived_at', 'remote_path', 'tags'])
            ->get()
            ->sortByDesc('taken_at');

        $byYear = $photos
            ->groupBy(fn ($photo) => $photo->taken_at?->year ?? $photo->created_at?->format('Y'))
            ->map(fn (Collection $group) => [
                'count' => $group->count(),
                'bytes' => (int) $group->sum('size_bytes'),
            ])
            ->sortKeysDesc();

        $busiestMonths = $photos
            ->groupBy(fn ($photo) => ($photo->taken_at ?? $photo->created_at)?->format('F Y'))
            ->map->count()
            ->sortDesc()
            ->take(5);

        $topTags = collect($photos->pluck('tags')->flatten())
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10);

        return view('insights', [
            'user' => $user,
            'summary' => [
                'total' => $photos->count(),
                'archived' => $photos->whereNotNull('archived_at')->count(),
                'backedUp' => $photos->whereNotNull('remote_path')->count(),
                'bytes' => (int) $photos->sum('size_bytes'),
                'limitBytes' => $user->storageLimitBytes(),
            ],
            'byYear' => $byYear,
            'busiestMonths' => $busiestMonths,
            'topTags' => $topTags,
            'largest' => $photos->sortByDesc('size_bytes')->take(5)->values(),
        ]);
    }

    /**
     * Suggestions for freeing up space: the largest photos and photos that are
     * byte-for-byte duplicates of each other.
     */
    public function cleanup(Request $request): View
    {
        $user = $request->user();

        $photos = $user->photos()
            ->notArchived()
            ->select(['id', 'original_filename', 'file_path', 'thumbnail_path', 'file_hash', 'size_bytes', 'taken_at', 'created_at'])
            ->get();

        $largest = $photos->sortByDesc('size_bytes')->take(10)->values();

        $duplicates = $photos
            ->filter(fn ($photo) => filled($photo->file_hash))
            ->groupBy('file_hash')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group) => $group->sortBy('id')->values())
            ->values();

        $duplicateCount = $duplicates->reduce(
            fn (int $carry, Collection $group) => $carry + $group->count() - 1,
            0,
        );

        return view('cleanup', [
            'user' => $user,
            'largest' => $largest,
            'duplicates' => $duplicates,
            'duplicateCount' => $duplicateCount,
            'archivedCount' => $user->photos()->archived()->count(),
        ]);
    }
}
