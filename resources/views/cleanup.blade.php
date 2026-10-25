<x-app-layout :title="'Cleanup suggestions'">
    <div class="insights-page">
        <div class="dashboard__top">
            <div>
                <h1 class="page-title">Cleanup suggestions</h1>
            </div>
        </div>

        @php
            $fmt = fn ($bytes) => $bytes >= 1048576
                ? number_format($bytes / 1048576, 1) . ' MB'
                : number_format($bytes / 1024, 0) . ' KB';

            $wasteBytes = $duplicates->reduce(
                fn (int $carry, $group) => $carry + ($group->count() - 1) * $group->last()->size_bytes,
                0,
            );

            $tabs = [
                'duplicates' => ['label' => 'Duplicate photos', 'count' => $duplicateCount],
                'large' => ['label' => 'Large files', 'count' => $largest->count()],
                'albums' => ['label' => 'Not in any album', 'count' => $notInAlbum->count()],
                'description' => ['label' => 'Missing description', 'count' => $missingDescription->count()],
            ];
            $activeTab = 'duplicates';
        @endphp

        <div class="tabbar" data-tabs>
            <div class="tabbar__list" role="tablist" aria-label="Cleanup categories">
                @foreach ($tabs as $key => $tab)
                    <button type="button"
                            class="tabbar__btn @if ($key === $activeTab) is-active @endif"
                            role="tab"
                            id="tab-{{ $key }}"
                            aria-selected="{{ $key === $activeTab ? 'true' : 'false' }}"
                            aria-controls="panel-{{ $key }}"
                            data-tab="{{ $key }}">
                        <span>{{ $tab['label'] }}</span>
                        <span class="badge">{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <section class="panel tabpanel" role="tabpanel" id="panel-duplicates" aria-labelledby="tab-duplicates" data-panel="duplicates" @if ($activeTab !== 'duplicates') hidden @endif>
            <h2 class="panel__heading" id="duplicates-heading">Duplicate photos</h2>

            @if ($duplicates->isEmpty())
                
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

        <section class="panel tabpanel" role="tabpanel" id="panel-large" aria-labelledby="tab-large" data-panel="large" @if ($activeTab !== 'large') hidden @endif>
            <h2 class="panel__heading" id="largest-heading">Largest photos</h2>

            @if ($largest->isEmpty())
                
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

        <section class="panel tabpanel" role="tabpanel" id="panel-albums" aria-labelledby="tab-albums" data-panel="albums" @if ($activeTab !== 'albums') hidden @endif>
            <h2 class="panel__heading" id="albums-heading">Not in any album</h2>

            @if ($notInAlbum->isEmpty())
                
            @else
                <ul class="photo-row-list">
                    @foreach ($notInAlbum as $photo)
                        <li class="photo-row">
                            <img class="photo-row__thumb" src="{{ $photo->thumbnailUrl() }}" alt="" loading="lazy">
                            <a class="photo-row__name" href="{{ route('photos.show', $photo) }}">{{ $photo->original_filename }}</a>
                            <span class="photo-row__meta">
                                {{ $fmt($photo->size_bytes) }}@if ($photo->taken_at)
                                    &middot; {{ $photo->taken_at->format('M j, Y') }}@endif
                            </span>
                            <a class="btn btn--ghost btn--sm" href="{{ route('photos.show', $photo) }}#albums">Add to an album</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel tabpanel" role="tabpanel" id="panel-description" aria-labelledby="tab-description" data-panel="description" @if ($activeTab !== 'description') hidden @endif>
            <h2 class="panel__heading" id="description-heading">Missing description</h2>

            @if ($missingDescription->isEmpty())
                
            @else
                <ul class="photo-row-list">
                    @foreach ($missingDescription as $photo)
                        <li class="photo-row">
                            <img class="photo-row__thumb" src="{{ $photo->thumbnailUrl() }}" alt="{{ $photo->description() }}" loading="lazy">
                            <a class="photo-row__name" href="{{ route('photos.show', $photo) }}">{{ $photo->original_filename }}</a>
                            <span class="photo-row__meta">Automatic alt text</span>
                            <a class="btn btn--ghost btn--sm" href="{{ route('photos.show', $photo) }}#details">Add description</a>
                        </li>
                    @endforeach
                </ul>
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
