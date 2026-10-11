<x-app-layout :title="'Cleanup suggestions'">
    <div class="insights-page">
        <div class="dashboard__top">
            <div>
                <h1 class="page-title">Cleanup suggestions</h1>
                <p class="page-subtitle">Ways to shrink your library: biggest files and exact duplicates.</p>
            </div>
        </div>

        @php
            $fmt = fn ($bytes) => $bytes >= 1048576
                ? number_format($bytes / 1048576, 1) . ' MB'
                : number_format($bytes / 1024, 0) . ' KB';
        @endphp

        @php
            $wasteBytes = $duplicates->reduce(
                fn (int $carry, $group) => $carry + ($group->count() - 1) * $group->last()->size_bytes,
                0,
            );
        @endphp

        <section class="panel" aria-labelledby="largest-heading">
            <h2 class="panel__heading" id="largest-heading">Largest photos</h2>
            <p class="panel__subheading">10 biggest photos in your library. Big files are rare, but they eat the most
                space.</p>

            @if ($largest->isEmpty())
                <p class="empty-note">No photos uploaded yet.</p>
            @else
                <ul class="photo-row-list">
                    @foreach ($largest as $index => $photo)
                        <li class="photo-row">
                            <span class="photo-row__rank">{{ $index + 1 }}</span>
                            <img class="photo-row__thumb" src="{{ $photo->thumbnailUrl() }}" alt="" loading="lazy">
                            <a class="photo-row__name" href="{{ route('photos.show', $photo) }}">{{ $photo->original_filename }}</a>
                            <span class="photo-row__meta">
                                {{ $fmt($photo->size_bytes) }}@if ($photo->taken_at)
                                    &middot; {{ $photo->taken_at->format('M j, Y') }}@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel" aria-labelledby="duplicates-heading">
            <h2 class="panel__heading" id="duplicates-heading">Duplicate photos</h2>
            <p class="panel__subheading">Photos that share the exact same byte content. Keeping one copy of each group
                would free up {{ $fmt($wasteBytes) }} ({{ $duplicateCount }} extra
                photo{{ $duplicateCount === 1 ? '' : 's' }}).</p>

            @if ($duplicates->isEmpty())
                <p class="empty-note">No duplicates found. If you spot a match, check the largest photos above first.</p>
            @else
                @foreach ($duplicates as $group)
                    <div class="duplicate-group">
                        <h3 class="duplicate-group__title">{{ $group->count() }} copies of
                            &ldquo;{{ $group->last()->original_filename }}&rdquo;</h3>
                        <ul class="photo-row-list">
                            @foreach ($group as $photo)
                                <li class="photo-row">
                                    <img class="photo-row__thumb" src="{{ $photo->thumbnailUrl() }}" alt="" loading="lazy">
                                    <a class="photo-row__name" href="{{ route('photos.show', $photo) }}">{{ $photo->original_filename }}</a>
                                    <span class="photo-row__meta">Uploaded {{ $photo->taken_at?->format('M j, Y') ?? $photo->created_at->format('M j, Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif
        </section>

        @if ($archivedCount > 0)
            <section class="panel" aria-labelledby="archived-note">
                <h2 class="panel__heading" id="archived-note">Already archived</h2>
                <p class="panel__subheading">{{ $archivedCount }} photo{{ $archivedCount === 1 ? '' : 's' }} are in your
                    archive. They no longer count towards storage, but can be restored anytime from the
                    <a class="link" href="{{ route('archive') }}">Archive</a> tab.</p>
            </section>
        @endif
    </div>
</x-app-layout>