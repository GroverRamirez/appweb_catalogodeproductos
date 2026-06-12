<?php

namespace App\Http\Middleware;

use App\Support\AdminGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffTwoFactor
{
    /**
     * Obliga al staff (propietario/encargado/vendedor) a tener la verificación en
     * dos pasos confirmada antes de acceder al panel administrativo.
     *
     * No produce bloqueo: la página de enrolamiento (`security.edit`), el cierre de
     * sesión y los endpoints de Fortify viven fuera del grupo `admin`, de modo que el
     * usuario siempre puede activar su 2FA.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && AdminGuard::canAccessAdmin($user)
            && $user->two_factor_confirmed_at === null
        ) {
            return redirect()
                ->route('security.edit')
                ->with('error', 'Debes activar la verificación en dos pasos para acceder al panel.');
        }

        return $next($request);
    }
}
