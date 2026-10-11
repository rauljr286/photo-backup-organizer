<?php

namespace App\Providers;

use App\Listeners\LockoutListener;
use App\Listeners\LoginFailedListener;
use App\Listeners\LoginSuccessfulListener;
use App\Listeners\LogoutListener;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.custom');
        Paginator::defaultSimpleView('pagination.custom');

        // Google is not bundled with Socialite; the socialiteproviders package
        // registers its driver through this event.
        Event::listen(SocialiteWasCalled::class, GoogleExtendSocialite::class.'@handle');

        Event::listen(Login::class, LoginSuccessfulListener::class);
        Event::listen(Failed::class, LoginFailedListener::class);
        Event::listen(Logout::class, LogoutListener::class);
        Event::listen(Lockout::class, LockoutListener::class);
    }
}
