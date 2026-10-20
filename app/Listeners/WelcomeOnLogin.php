<?php

namespace App\Listeners;

use App\Models\Setting;
use App\Support\Toast;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Saluda al usuario cuando inicia sesión (contraseña, passkey, Google o el
 * registro de una cuenta nueva). Se engancha al evento y no a cada respuesta
 * de login para cubrir todos los caminos de una sola vez.
 */
class WelcomeOnLogin
{
    public function handle(Login $event): void
    {
        $name = Str::of((string) $event->user->name)->trim()->before(' ')->toString();
        $store = Setting::get('store_name', 'Mi Catálogo');

        // El usuario recién creado llega acá desde el registro o el primer
        // ingreso con Google.
        if ($event->user->wasRecentlyCreated) {
            Toast::success("¡Tu cuenta está lista, {$name}!", "Te damos la bienvenida a {$store}.");

            return;
        }

        // Sesión restaurada con la cookie "recordarme": no hubo formulario.
        if (Auth::guard($event->guard)->viaRemember()) {
            Toast::success("¡Hola de nuevo, {$name}!");

            return;
        }

        Toast::success("¡Hola, {$name}!", "Te damos la bienvenida a {$store}.");
    }
}
