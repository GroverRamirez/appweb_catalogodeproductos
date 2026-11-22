<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Support\AdminGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El cliente que entra con Google o con una cuenta web no trae CI ni
 * teléfono, y sin ellos la tienda no lo puede identificar en el mostrador ni
 * contactarlo. Hasta que los complete, el catálogo lo manda al formulario.
 *
 * El staff no pasa por aquí: su ficha no es de cliente.
 */
class EnsureClientProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $request->isMethod('GET') || AdminGuard::canAccessAdmin($user)) {
            return $next($request);
        }

        if (! Client::profileCompleteFor($user)) {
            // Se recuerda adónde iba, para devolverlo ahí después de guardar.
            if (! $request->expectsJson()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('client-profile.edit');
        }

        return $next($request);
    }
}
