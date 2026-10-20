<x-app-layout :title="'Insights'">
    <div class="insights-page">
        <div class="dashboard__top">
            <div>
                <h1 class="page-title">Insights</h1>
                <p class="page-subtitle">A quick look at how your photo library has grown.</p>
            </div>
            <div class="dashboard__top-actions">
                <a class="btn btn--ghost" href="{{ route('cleanup') }}">Suggestions for freeing space</a>
            </div>
        </div>

        @php
            $fmt = fn ($bytes) => $bytes >= 1048576
                ? number_format($bytes / 1048576, 1) . ' MB'
                : number_format($bytes / 1024, 0) . ' KB';
            $usedPercent = $summary['limitBytes'] > 0
                ? (int) round(min($summary['bytes'] / $summary['limitBytes'] * 100, 999))
                : 0;
        @endphp

        <div class="stat-grid">
            <div class="stat">
                <span class="stat__icon stat__icon--primary" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>
                </span>
                <span class="stat__text">
                    <span class="stat__value">{{ number_format($summary['total']) }}</span>
                    <span class="stat__label">Photos</span>
                </span>
            </div>

            <div class="stat">
                <span class="stat__icon stat__icon--teal" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14a9 3 0 0 0 18 0V5"></path><path d="M3 12a9 3 0 0 0 18 0"></path></svg>
                </span>
                <span class="stat__text">
                    <span class="stat__value">{{ $fmt($summary['bytes']) }}</span>
                    <span class="stat__label">{{ $usedPercent }}% of your storage limit</span>
                </span>
            </div>

            <div class="stat">
                <span class="stat__icon stat__icon--slate" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><path d="M9 14h6"></path></svg>
                </span>
                <span class="stat__text">
                    <span class="stat__value">{{ $summary['archived'] }}</span>
                    <span class="stat__label">Archived</span>
                </span>
            </div>

            <div class="stat">
                <span class="stat__icon stat__icon--accent" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path></svg>
                </span>
                <span class="stat__text">
                    <span class="stat__value">{{ $summary['backedUp'] }}</span>
                    <span class="stat__label">Backed up to cloud</span>
                </span>
            </div>
        </div>

        @php
            $peakUploads = max(1, $uploadsByMonth->max('count'));
        @endphp

        <div class="insights-grid">
            <section class="panel" aria-labelledby="uploads-heading">
                <h2 class="panel__heading" id="uploads-heading">Uploads, last 12 months</h2>
                <p class="panel__subheading">How many photos you added each month, newest on the right.</p>

                @if ($summary['total'] === 0)
                    <p class="empty-note">No photos uploaded yet.</p>
                @else
                    <div class="bar-chart-wrap">
                        <ul class="bar-chart">
                            @foreach ($uploadsByMonth as $slot)
                                @php
                                    $height = $slot['count'] > 0
                                        ? max(6, (int) round($slot['count'] / $peakUploads * 100))
                                        : 0;
                                @endphp
                                <li class="bar-chart__col">
                                    <span class="bar-chart__value @if ($slot['count'] === 0) is-empty @endif">{{ $slot['count'] }}</span>
                                    <span class="bar-chart__bar" style="height: {{ $height }}%" role="img"
                                          aria-label="{{ $slot['label'] }}: {{ $slot['count'] }} photo{{ $slot['count'] === 1 ? '' : 's' }}"></span>
                                    <span class="bar-chart__label">
                                        <span class="bar-chart__month">{{ $slot['month'] }}</span>
                                        <span class="bar-chart__year">{{ $slot['year'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        <section class="panel" aria-labelledby="by-year-heading">
            <h2 class="panel__heading" id="by-year-heading">Photos &amp; storage by year</h2>

            @if ($byYear->isEmpty())
                <p class="empty-note">No photos uploaded yet.</p>
            @else
                <table class="insight-table">
                    <thead>
                        <tr>
                            <th scope="col">Year</th>
                            <th scope="col" class="insight-table__num">Photos</th>
                            <th scope="col" class="insight-table__num">Storage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byYear as $year => $row)
                            <tr>
                                <td data-label="Year"><strong>{{ $year }}</strong></td>
                                <td class="insight-table__num" data-label="Photos">{{ number_format($row['count']) }}</td>
                                <td class="insight-table__num" data-label="Storage">{{ $fmt($row['bytes']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="panel" aria-labelledby="largest-heading">
            <h2 class="panel__heading" id="largest-heading">Largest photos</h2>

            @if ($largest->isEmpty())
                <p class="empty-note">No photos uploaded yet.</p>
            @else
                <ul class="photo-row-list">
                    @foreach ($largest as $photo)
                        <li class="photo-row">
                            <img class="photo-row__thumb" src="{{ $photo->thumbnailUrl() }}" alt="" loading="lazy">
                            <a class="photo-row__name" href="{{ route('photos.show', $photo) }}">{{ $photo->original_filename }}</a>
                            <span class="photo-row__meta">{{ $fmt($photo->size_bytes) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-app-layout>