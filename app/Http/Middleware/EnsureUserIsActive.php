<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca de la sesión a una cuenta desactivada, entre por contraseña, Google,
 * passkey o la cookie de "recordarme".
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Solo una cuenta desactivada a propósito (false). Si la columna aún no
        // existe porque falta correr la migración, el valor llega null y la
        // cuenta no debe quedar bloqueada.
        if ($user && $user->is_active === false) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Tu cuenta está desactivada. Habla con el propietario de la tienda.');
        }

        return $next($request);
    }
}
