<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    /**
     * In-app privacy policy.
     */
    public function privacy(): View
    {
        return view('legal.privacy', [
            'supportEmail' => config('photobackup.support_email', 'support@example.com'),
        ]);
    }

    /**
     * In-app terms of service.
     */
    public function terms(): View
    {
        return view('legal.terms', [
            'supportEmail' => config('photobackup.support_email', 'support@example.com'),
        ]);
    }
}
