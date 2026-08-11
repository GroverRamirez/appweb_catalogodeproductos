<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminGuard;
use App\Support\AuthRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Spatie\Permission\Models\Role;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')
                ->with('error', 'El enlace de Google expiró o no es válido. Probá de nuevo.');
        } catch (\Throwable) {
            return redirect()->route('login')
                ->with('error', 'No se pudo completar el ingreso con Google. Probá de nuevo.');
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        // El login con Google es solo para clientes: el staff (con permisos)
        // siempre entra con su contraseña, nunca por este camino.
        if ($user && AdminGuard::canAccessAdmin($user)) {
            return redirect()->route('login')
                ->with('error', 'Esta cuenta es de staff: iniciá sesión con tu contraseña.');
        }

        if ($user) {
            if (! $user->google_id) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }
        } else {
            $user = DB::transaction(function () use ($googleUser) {
                $newUser = User::create([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $googleUser->getEmail(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    // Google ya verificó el email; la contraseña no se usa (no hay
                    // formulario de login por contraseña para esta cuenta), pero la
                    // columna es NOT NULL así que generamos una aleatoria.
                    'password' => Hash::make(Str::random(40)),
                ]);
                $newUser->email_verified_at = now();
                $newUser->save();

                if (Role::where('name', 'cliente')->exists()) {
                    $newUser->assignRole('cliente');
                }

                return $newUser;
            });
        }

        Auth::login($user, remember: true);

        return redirect()->intended(AuthRedirect::for($user));
    }
}
