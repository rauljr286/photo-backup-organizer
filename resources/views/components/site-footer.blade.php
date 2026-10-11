@props(['supportEmail' => null])

<footer class="site-footer">
    <div class="site-footer__inner">
        <nav class="site-footer__nav" aria-label="Footer">
            <a href="{{ route('terms') }}">Terms</a>
            <a href="{{ route('privacy') }}">Privacy</a>
            @if ($supportEmail)
                <a href="mailto:{{ $supportEmail }}">Help &amp; support</a>
            @endif
        </nav>
    </div>
</footer>
