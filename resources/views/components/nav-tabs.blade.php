@props(['compact' => false])

@php
    $archivedCount = auth()->user()?->photos()->notPrivate()->archived()->count() ?? 0;
    $items = array_filter(config('navigation.primary'), fn ($item) => ($item['desktop'] ?? true) !== false);
@endphp

<nav class="tabnav @if ($compact) tabnav--compact @endif" aria-label="Primary">
    @foreach ($items as $item)
        @php($active = request()->routeIs(...$item['active']))
        <a href="{{ route($item['route']) }}"
           @class(['tabnav__link', 'is-active' => $active])
           @if ($active) aria-current="page" @endif
           @if ($item['route'] === 'private') data-private-gate @endif>
            <svg class="tabnav__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $item['icon'] !!}</svg>
            <span>{{ $item['label'] }}</span>
            @if (($item['badge'] ?? null) === 'archived' && $archivedCount > 0)
                <span class="badge tabnav__badge">{{ $archivedCount }}</span>
            @endif
        </a>
    @endforeach
</nav>
