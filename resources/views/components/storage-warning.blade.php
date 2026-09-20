@props(['user' => null])

@php
    $user = $user ?? auth()->user();
    if (! $user) {
        return;
    }

    $usedPercent = $user->storageUsedPercent();
    $usedMb = number_format($user->storageUsedBytes() / 1024 / 1024, 1);
    $limitMb = number_format($user->storageLimitBytes() / 1024 / 1024, 0);
    $trashed = $user->photos()->onlyTrashed()->count();
@endphp

{{-- Warn once the user crosses 80% of their quota. Dismissal is remembered in
     the browser (localStorage) and the banner returns on the next visit unless
     space is freed. --}}
@if ($usedPercent >= 80 && $usedPercent < 100)
    <aside class="storage-warning" data-storage-warning data-user-id="{{ $user->id }}">
        <svg class="storage-warning__icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <div class="storage-warning__body">
            <p class="storage-warning__title">Storage nearly full — {{ $usedPercent }}% used</p>
            <p class="storage-warning__text">
                You have used {{ $usedMb }} of {{ $limitMb }} MB.
                @if ($trashed > 0)
                    Some space is pending cleanup: {{ $trashed }} photo(s) sit in your trash.
                @endif
                Consider emptying the trash or removing unused photos to free up space.
            </p>
        </div>
        <button type="button" class="storage-warning__close" data-storage-warning-dismiss aria-label="Dismiss storage warning">&times;</button>
    </aside>
@endif