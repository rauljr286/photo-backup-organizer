@php($account = config('navigation.account'))

<form method="POST" action="{{ route('logout') }}" class="user-menu__logout">
    @csrf
    <button type="submit" role="menuitem" class="user-menu__item user-menu__item--button">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $account['signout']['icon'] !!}</svg>
        <span>{{ $account['signout']['label'] }}</span>
    </button>
</form>
