<meta name="color-scheme" content="light dark">

{{-- Resolve the theme before first paint so there is no flash of the wrong
     colour scheme. A stored choice wins; otherwise follow the OS setting. --}}
<script>
    (function () {
        var theme;
        try {
            theme = localStorage.getItem('pbo-theme');
        } catch (e) {}
        if (theme !== 'light' && theme !== 'dark') {
            theme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
                ? 'dark'
                : 'light';
        }
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>

{{-- PWA: install metadata. Theme colours cover both schemes; the icons and
     manifest are static public files (no session data). --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#0f766e">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0b1020">
<link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Photo Backup">
