@props(['title' => 'Welcome'])

<!DOCTYPE html>
<html lang="en" class="welcome-html">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-head />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Back up, organize and browse your photos securely and privately.">
    <title>{{ $title }} - Photo Backup Organizer</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="welcome-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <main id="main-content" class="page--welcome">
        {{ $slot }}
    </main>

    <div id="auth-live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
