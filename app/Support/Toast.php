<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Notificaciones (toasts) que el front muestra una sola vez.
 *
 * Viajan por el canal flash de Inertia y no como prop compartida: así no quedan
 * guardadas en el historial del navegador y volver atrás no repite el aviso.
 *
 * Los controladores pueden seguir usando ->with('success', 'Texto') o, si el
 * aviso necesita una segunda línea, ->with('success', ['message' => ..., 'description' => ...]).
 */
class Toast
{
    public const TYPES = ['success', 'error', 'warning', 'info'];

    public static function success(string $message, ?string $description = null): void
    {
        static::push('success', $message, $description);
    }

    public static function error(string $message, ?string $description = null): void
    {
        static::push('error', $message, $description);
    }

    public static function warning(string $message, ?string $description = null): void
    {
        static::push('warning', $message, $description);
    }

    public static function info(string $message, ?string $description = null): void
    {
        static::push('info', $message, $description);
    }

    public static function push(string $type, string $message, ?string $description = null): void
    {
        if (! request()->hasSession()) {
            return;
        }

        $toast = array_filter(
            ['type' => $type, 'message' => $message, 'description' => $description],
            fn ($value) => $value !== null && $value !== '',
        );

        Inertia::flash('toasts', [...(Inertia::getFlashed()['toasts'] ?? []), $toast]);
    }

    /**
     * Convierte los mensajes flash clásicos de la sesión (los que dejó el
     * request anterior) en notificaciones.
     */
    public static function fromSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        foreach (static::TYPES as $type) {
            $value = $request->session()->get($type);

            if (is_string($value) && $value !== '') {
                static::push($type, $value);
            } elseif (is_array($value) && is_string($value['message'] ?? null)) {
                static::push($type, $value['message'], $value['description'] ?? null);
            }
        }
    }
}
