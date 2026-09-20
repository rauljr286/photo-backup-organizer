<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Back up, organize and browse your photos securely and privately.">
    <title>{{ $title ?? 'Photo Backup Organizer' }} - Photo Backup</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="page-guest-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <main id="main-content" class="page page--guest">
        <div class="auth-shell">
            <a href="{{ route('login') }}" class="brand auth-shell__brand" aria-label="Photo Backup Organizer home">
                <svg class="brand__icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path vector-effect="non-scaling-stroke" transform="translate(12 5) scale(0.5) translate(-12 -11)" d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
                    <path d="M5 11.5h3.6l1.2-2h4.4l1.2 2H19a2 2 0 0 1 2 2v4.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13.5a2 2 0 0 1 2-2Z"/>
                    <circle cx="12" cy="15.5" r="2.5"/>
                </svg>
                <span>Photo Backup Organizer</span>
            </a>

            @if (session('status'))
                <div class="flash flash--success" role="status">
                    <p>{{ session('status') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="flash flash--error" role="alert">
                    <p class="flash__title">Please fix the following:</p>
                    <ul class="flash__list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>
</body>
</html>