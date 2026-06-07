<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supported = ['es', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');
        if (! in_array($locale, $this->supported, true)) {
            $locale = config('app.locale', 'es');
        }
        App::setLocale($locale);

        return $next($request);
    }
}
