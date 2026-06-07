<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function set(Request $request, string $locale): RedirectResponse
    {
        $allowed = ['es', 'en'];
        if (! in_array($locale, $allowed, true)) {
            $locale = 'es';
        }

        return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
