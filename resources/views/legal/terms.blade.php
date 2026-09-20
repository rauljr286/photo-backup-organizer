<x-app-layout :title="'Terms and Conditions'">
    <article class="legal">
        <h1 class="page-title">Terms and Conditions</h1>
        <p class="legal__updated">Last updated: {{ date('F j, Y') }}</p>

        <p>By using this app, you agree to the following:</p>

        @include('legal.partials.terms-content', ['supportEmail' => $supportEmail])
    </article>
</x-app-layout>