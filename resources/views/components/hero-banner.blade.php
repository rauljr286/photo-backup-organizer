@props(['greeting', 'subtitle' => null, 'eyebrow' => null, 'stats' => [], 'user' => null])

@php
    $icons = [
        'photo' => '<path d="m21 15-5-5L5 21"></path><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle>',
        'album' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>',
        'storage' => '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14a9 3 0 0 0 18 0V5"></path><path d="M3 12a9 3 0 0 0 18 0"></path>',
        'trash' => '<path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>',
        'cloud' => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path>',
    ];
@endphp

<section class="hero">
    <div class="hero__decor" aria-hidden="true">
        <div class="hero__orb hero__orb--one"></div>
        <div class="hero__orb hero__orb--two"></div>
    </div>

    <div class="hero__content {{ $user ? 'hero__content--profile' : '' }}">
        @if ($user)
            <span class="hero__avatar">
                <x-avatar :user="$user" size="lg"></x-avatar>
            </span>
        @endif

        <div class="hero__intro">
            @if ($eyebrow)
                <p class="hero__eyebrow">{{ $eyebrow }}</p>
            @endif

            <h1 class="hero__title">{{ $greeting }}</h1>

            @if ($subtitle)
                <p class="hero__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if (! empty($stats))
        <div class="hero__stats">
            @foreach ($stats as $stat)
                <div class="stat">
                    <span class="stat__icon {{ $stat['tint'] ?? 'stat__icon--primary' }}" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            {!! $icons[$stat['icon']] ?? $icons['photo'] !!}
                        </svg>
                    </span>
                    <span class="stat__text">
                        <span class="stat__value">{{ $stat['value'] }}</span>
                        <span class="stat__label">{{ $stat['label'] }}</span>
                        @if (($stat['note'] ?? null) !== null)
                            <span class="stat__note">{{ $stat['note'] }}</span>
                        @endif
                    </span>
                    @if (($stat['meter'] ?? null) !== null)
                        <span class="stat__meter" role="presentation">
                            <span class="stat__meter-fill" style="width: {{ min((float) $stat['meter'], 100) }}%"></span>
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>