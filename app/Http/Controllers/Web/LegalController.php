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
            'retentionDays' => (int) config('photobackup.trash_retention_days', 30),
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
