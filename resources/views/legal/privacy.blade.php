<x-app-layout :title="'Privacy Policy'">
    <article class="legal">
        <h1 class="page-title">Privacy Policy</h1>
        <p class="legal__updated">Last updated: {{ date('F j, Y') }}</p>

        @include('legal.partials.privacy-content', [
            'retentionDays' => $retentionDays,
            'supportEmail' => $supportEmail,
        ])
    </article>
</x-app-layout>