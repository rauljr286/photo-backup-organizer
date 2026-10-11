@props([
    'variant' => null,
    'label' => null,
])

@php
    $classes = ['theme-toggle'];
    if ($variant === 'sidebar') {
        $classes[] = 'theme-toggle--sidebar';
    }
@endphp

<button
    type="button"
    {{ $attributes->class($classes) }}
    data-theme-toggle
    aria-label="Switch to light mode"
    aria-pressed="true"
>
    <svg class="theme-toggle__icon theme-toggle__icon--sun" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <circle cx="12" cy="12" r="5"/>
        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
    </svg>
    <svg class="theme-toggle__icon theme-toggle__icon--moon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
    </svg>
    @if ($label)
        <span class="theme-toggle__text" data-theme-text>{{ $label }}</span>
    @endif
</button>
