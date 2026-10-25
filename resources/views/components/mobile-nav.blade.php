@php
    $primary = config('navigation.primary');
    $tabs = array_filter($primary, fn ($item) => ($item['mobile'] ?? 'more') === 'tab');
    $more = array_filter($primary, fn ($item) => ($item['mobile'] ?? 'more') === 'more');
    $phoneRole = fn ($item) => $item['phone'] ?? ($item['mobile'] ?? 'more');
    $phoneTabs = array_filter($primary, fn ($item) => $phoneRole($item) === 'tab');
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
                    <span class="bottom-nav__badge" aria-label="{{ $archivedCount }} archived photos">{{ $archivedCount }}</span>
                @endif
            </span>
            <span class="bottom-nav__label">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>

<div class="bottom-nav__more-wrap">
    <div class="user-menu bottom-nav__more" data-user-menu>
        <button type="button" class="bottom-nav__item user-menu__trigger" data-user-menu-trigger
                aria-haspopup="true" aria-expanded="false" aria-controls="bottom-more-panel" aria-label="More">
        </button>

        <div class="user-menu__panel user-menu__panel--up" id="bottom-more-panel" data-user-menu-panel role="menu" aria-label="More" hidden>
            @foreach ($more as $item)
                <a class="user-menu__item" href="{{ route($item['route']) }}" role="menuitem"
                   @if ($item['route'] === 'private') data-private-gate @endif>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $item['icon'] !!}</svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach

            <div class="user-menu__divider" role="separator"></div>

            <x-sign-out />
        </div>
    </div>
</div>

{{-- Phone (<=767.98px) bottom bar. The bar above keeps
     its current 768-899.98px behaviour untouched; CSS swaps between the two.
     Archive and Cleanup are now in the bottom bar as real tabs with the
     archived count shown as a notification dot/bubble on Archive. The
     profile initials are shown in the top bar only. --}}
<nav class="bottom-nav bottom-nav--phone" aria-label="Primary">
    @foreach ($phoneTabs as $item)
        @php($active = request()->routeIs(...$item['active']))
        <a href="{{ route($item['route']) }}{{ $item['suffix'] ?? '' }}"
           @class(['bottom-nav__item', 'is-active' => $active])
           @if ($active) aria-current="page" @endif>
            <span class="bottom-nav__icon-wrap">
                <svg class="bottom-nav__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $item['phone_icon'] ?? $item['icon'] !!}</svg>
                @if (($item['badge'] ?? null) === 'archived' && $archivedCount > 0)
                    <span class="bottom-nav__badge" aria-label="{{ $archivedCount }} archived photos">{{ $archivedCount }}</span>
                @endif
            </span>
            <span class="bottom-nav__label">{{ $item['mobile_label'] ?? $item['label'] }}</span>
        </a>
    @endforeach
</nav>
