@props(['user', 'size' => 'md', 'class' => ''])

@php
    $url = $user->avatarUrl()
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path)
        ? $user->avatarUrl()
        : null;
@endphp

<span class="avatar avatar--{{ $size }} {{ $class }}" role="img" aria-label="{{ $url ? 'Profile picture of '.$user->name : 'No profile picture set' }}">
    @if ($url)
        <img class="avatar__img" src="{{ $url }}" alt="{{ $user->name }}">
    @else
        <svg class="avatar__placeholder" viewBox="0 0 24 24" width="60%" height="60%" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M20 21a8 8 0 0 0-16 0"></path>
            <circle cx="12" cy="7" r="4"></circle>
        </svg>
    @endif
</span>