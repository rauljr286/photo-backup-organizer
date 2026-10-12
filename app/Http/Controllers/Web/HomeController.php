<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Landing page. The home route is a controller action (not a closure) so
     * `php artisan route:cache` works in production.
     */
    public function __invoke(): View
    {
        return view('welcome', [
            'supportEmail' => config('photobackup.support_email', 'support@example.com'),
        ]);
    }
}
