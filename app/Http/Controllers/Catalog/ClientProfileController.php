<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CompleteClientProfileRequest;
use App\Models\Client;
use App\Support\AdminGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Completa tus datos": CI y teléfono del cliente con cuenta web, guardados en
 * su ficha de `clientes` (la misma que usa el formulario de venta para buscar
 * por CI), no en `users`.
 */
class ClientProfileController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (AdminGuard::canAccessAdmin($user)) {
            return redirect('/admin');
        }

        $client = Client::where('user_id', $user->id)->first();

        return Inertia::render('auth/CompleteProfile', [
            'idCard' => $client?->id_card,
            'phone' => $client?->phone,
        ]);
    }

    public function update(CompleteClientProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (AdminGuard::canAccessAdmin($user)) {
            return redirect('/admin');
        }

        $data = $request->validated();

        try {
            Client::completeForUser($user, $data['id_card'], $data['phone']);
        } catch (\DomainException) {
            throw ValidationException::withMessages([
                'phone' => 'Ese teléfono ya está registrado en otra cuenta. Si es tuyo, contáctanos.',
            ]);
        }

        return redirect()->intended('/')
            ->with('success', '¡Listo! Tus datos quedaron guardados.');
    }
}
