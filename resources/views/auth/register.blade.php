<x-guest-layout title="Create an account">
    <x-auth-card
        state="{{ old('_auth_pane') === 'login' ? 'login' : 'register' }}"
        :agreed="$agreed"
        :supportEmail="$supportEmail"
    />
</x-guest-layout>
