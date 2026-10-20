<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InsightsController extends Controller
{
    /**
     * Aggregate the user's library: storage by year, monthly uploads and the
     * largest photos.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        $photos = $user->photos()
            ->where('is_private', false)
            ->select(['id', 'original_filename', 'file_path', 'thumbnail_path', 'mime_type', 'taken_at', 'created_at', 'file_hash', 'size_bytes', 'archived_at', 'remote_path', 'tags'])
            ->get()
            ->sortByDesc('taken_at');

        $byYear = $photos
            ->groupBy(fn ($photo) => $photo->created_at?->format('Y'))
            ->map(fn (Collection $group) => [
                'count' => $group->count(),
                'bytes' => (int) $group->sum('size_bytes'),
            ])
            ->sortKeysDesc();

        $uploadsByMonth = $this->uploadsByMonth($photos);

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
            'uploadsByMonth' => $uploadsByMonth,
            'largest' => $photos->sortByDesc('size_bytes')->take(5)->values(),
        ]);
    }

    /**
     * Upload counts for the trailing twelve months, oldest first.
     *
     * @return Collection<int, array{month: string, year: string, label: string, count: int}>
     */
    protected function uploadsByMonth(Collection $photos): Collection
    {
        $counts = $photos
            ->groupBy(fn ($photo) => $photo->created_at?->format('Y-m'))
            ->map->count();

        return collect(range(11, 0))
            ->map(function (int $monthsAgo) use ($counts) {
                $month = now()->subMonthsNoOverflow($monthsAgo);

                return [
                    'month' => $month->format('M'),
                    'year' => $month->format('y'),
                    'label' => $month->format('M Y'),
                    'count' => (int) ($counts[$month->format('Y-m')] ?? 0),
                ];
            })
            ->values();
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
            ->where('is_private', false)
            ->select(['id', 'original_filename', 'file_path', 'thumbnail_path', 'file_hash', 'size_bytes', 'alt_text', 'taken_at', 'created_at'])
            ->withCount('albums')
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

        $notInAlbum = $photos
            ->where('albums_count', 0)
            ->sortByDesc('size_bytes')
            ->values();

        $missingDescription = $photos
            ->filter(fn ($photo) => blank($photo->alt_text))
            ->sortByDesc('size_bytes')
            ->values();

        return view('cleanup', [
            'user' => $user,
            'largest' => $largest,
            'duplicates' => $duplicates,
            'duplicateCount' => $duplicateCount,
            'notInAlbum' => $notInAlbum,
            'missingDescription' => $missingDescription,
            'archivedCount' => $user->photos()->archived()->count(),
        ]);
    }
}
