<x-guest-layout title="Sign in">
    <x-auth-card
        state="{{ old('_auth_pane') === 'register' ? 'register' : 'login' }}"
        :agreed="session()->has('agreed_to_terms_at')"
        :supportEmail="config('photobackup.support_email', 'support@example.com')"
    />
</x-guest-layout>
