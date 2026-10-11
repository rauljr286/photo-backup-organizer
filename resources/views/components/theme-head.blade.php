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
