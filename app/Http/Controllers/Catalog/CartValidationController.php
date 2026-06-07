<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartValidationController extends Controller
{
    /**
     * Valida que los productos del carrito (localStorage) sigan activos y disponibles.
     *
     * Recibe:  { "ids": [1, 2, 3] }
     * Devuelve: { "removed": [2] }   ← IDs que ya no están activos o no existen
     *
     * El frontend elimina esos IDs del carrito automáticamente.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $requested = collect($request->input('ids'))->map(fn ($id) => (int) $id)->unique()->values();

        // IDs que aún existen y están activos (sin soft-delete)
        $activeIds = Product::whereIn('id', $requested)
            ->where('activo', true)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        // Lo que pidieron pero no está activo = a eliminar del carrito
        $removed = $requested->diff($activeIds)->values();

        return response()->json(['removed' => $removed]);
    }
}
