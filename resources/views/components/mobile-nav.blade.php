@php
    $primary = config('navigation.primary');
    $tabs = array_filter($primary, fn ($item) => ($item['mobile'] ?? 'more') === 'tab');
    $more = array_filter($primary, fn ($item) => ($item['mobile'] ?? 'more') === 'more');
    $account = config('navigation.account');
    $archivedCount = auth()->user()->photos()->notPrivate()->archived()->count();
@endphp

<nav class="bottom-nav" aria-label="Primary">
    @foreach ($tabs as $item)
        @php($active = request()->routeIs(...$item['active']))
        <a href="{{ route($item['route']) }}"
           @class(['bottom-nav__item', 'is-active' => $active])
           @if ($active) aria-current="page" @endif>
            <span class="bottom-nav__icon-wrap">
                <svg class="bottom-nav__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $item['icon'] !!}</svg>
                @if (($item['badge'] ?? null) === 'archived' && $archivedCount > 0)
                    <span class="badge bottom-nav__badge">{{ $archivedCount }}</span>
                @endif
            </span>
            <span class="bottom-nav__label">{{ $item['label'] }}</span>
        </a>
    @endforeach

    @php($profileActive = request()->routeIs(...$account['profile']['active']))
    <a href="{{ route($account['profile']['route']) }}"
       @class(['bottom-nav__item', 'is-active' => $profileActive])
       @if ($profileActive) aria-current="page" @endif>
        <span class="bottom-nav__icon-wrap">
            <svg class="bottom-nav__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $account['profile']['icon'] !!}</svg>
        </span>
        <span class="bottom-nav__label">{{ $account['profile']['short'] }}</span>
    </a>

    <div class="user-menu bottom-nav__more" data-user-menu>
        <button type="button" class="bottom-nav__item user-menu__trigger" data-user-menu-trigger
                aria-haspopup="true" aria-expanded="false" aria-controls="bottom-more-panel" aria-label="More">
            <svg class="bottom-nav__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <circle cx="5" cy="12" r="1.5"></circle>
                <circle cx="12" cy="12" r="1.5"></circle>
                <circle cx="19" cy="12" r="1.5"></circle>
            </svg>
            <span class="bottom-nav__label">More</span>
        </button>

        <div class="user-menu__panel user-menu__panel--up" id="bottom-more-panel" data-user-menu-panel role="menu" aria-label="More" hidden>
            @foreach ($more as $item)
                <a class="user-menu__item" href="{{ route($item['route']) }}" role="menuitem"
                   @if ($item['route'] === 'private') data-private-gate @endif>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $item['icon'] !!}</svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach

            <x-theme-toggle class="user-menu__item" label="Dark mode" role="menuitem" />

            <div class="user-menu__divider" role="separator"></div>

            <x-sign-out />
        </div>
    </div>
</nav>
