@php
    $authenticated = auth()->check();
    $showAuth = ! $authenticated && ($errors->any() || session('status'));
    $landingHidden = $showAuth ? ' hidden' : '';
    $authHidden = $showAuth ? '' : ' hidden';
    $ctaHref = $authenticated ? route('dashboard') : route('login');
    $ctaLabel = $authenticated ? 'Go to dashboard' : 'Get Started';
@endphp

<x-layouts.welcome title="Welcome">
    <div class="landing-screen" id="landing-screen"{!! $landingHidden !!}>
        <header class="landing-header">
            <a href="{{ route('home') }}" class="landing-logo" aria-label="Photo Backup Organizer home">
                <svg class="landing-logo__icon" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path vector-effect="non-scaling-stroke" transform="translate(12 5) scale(0.5) translate(-12 -11)" d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
                    <path d="M5 11.5h3.6l1.2-2h4.4l1.2 2H19a2 2 0 0 1 2 2v4.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13.5a2 2 0 0 1 2-2Z"/>
                    <circle cx="12" cy="15.5" r="2.5"/>
                </svg>
                <span class="landing-logo__text">Photo Backup Organizer</span>
            </a>

            <div class="landing-header__right">
                <x-theme-toggle class="landing-toggle" />
            </div>
        </header>

        <main class="landing-main">
            <div class="landing-hero">
                <p class="landing-tagline">A little order. A lot of peace.</p>
                <h1 class="landing-heading">Your memories.<br>Safe, sorted, together.</h1>
                <p class="landing-subtitle">Bring your photos home. Organize your library, clear duplicates, and back up with confidence. Your library stays yours.</p>

                <ul class="landing-features" aria-label="What you can do">
                    <li class="landing-feature">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21s7-3.5 7-9V6l-7-3-7 3v6c0 5.5 7 9 7 9Z"/></svg>
                        <span>Private by default</span>
                    </li>
                    <li class="landing-feature">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span>Duplicate cleanup</span>
                    </li>
                    <li class="landing-feature">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.59 13.41 11 3.83V3H3v8h.83l9.58 9.59a2 2 0 0 0 2.82 0l4.36-4.36a2 2 0 0 0 0-2.82Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
                        <span>Albums &amp; tags</span>
                    </li>
                    <li class="landing-feature">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
                        <span>Safe backups</span>
                    </li>
                </ul>

                <a href="{{ $ctaHref }}" class="btn btn--primary landing-cta" id="landing-get-started">
                    {{ $ctaLabel }}
                    <span class="landing-cta__arrow" aria-hidden="true">&rarr;</span>
                </a>
            </div>

            <div class="landing-camera-scene" id="landing-camera" aria-hidden="true">
                <div class="landing-photo landing-photo--p1"></div>
                <div class="landing-photo landing-photo--p2"></div>
                <div class="landing-photo landing-photo--p3"></div>
                <div class="landing-camera">
                    <div class="landing-camera__shutter"></div>
                    <div class="landing-camera__body">
                        <div class="landing-camera__flash"></div>
                        <div class="landing-camera__lens">
                            <div class="landing-camera__glass"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <x-site-footer :supportEmail="$supportEmail" />
    </div>

    <div class="welcome-auth" id="auth-container"{!! $authHidden !!}>
        <div class="welcome-auth__inner">
            @if (session('status'))
                <div class="flash flash--success" role="status">
                    <p>{{ session('status') }}</p>
                </div>
            @endif

            <x-auth-card
                state="{{ old('_auth_pane') === 'register' ? 'register' : 'login' }}"
                :agreed="session()->has('agreed_to_terms_at')"
                :supportEmail="$supportEmail"
            />
        </div>
    </div>
</x-layouts.welcome>