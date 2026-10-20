<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartValidationController extends Controller
{
    /**
     * Valida que los productos del carrito (localStorage) sigan activos y disponibles.
     *
     * Recibe:  { "ids": [1, 2, 3] }
     * Devuelve: { "removed": [2], "prices": { "1": 3866.0 } }
     *
     * El frontend elimina del carrito los IDs removidos y refresca los precios.
     * Los precios viajan porque el carrito vive en localStorage: si el tipo de
     * cambio se movió desde que el cliente agregó el producto, el carrito
     * mostraría la cotización de ayer hasta el checkout.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $requested = collect($request->input('ids'))->map(fn ($id) => (int) $id)->unique()->values();

        // Productos que aún existen y están activos (sin soft-delete)
        $products = Product::whereIn('id', $requested)
            ->where('activo', true)
            ->whereNull('deleted_at')
            ->get(['id', 'precio', 'precio_oferta']);

        $activeIds = $products->pluck('id')->map(fn ($id) => (int) $id);

        // Lo que pidieron pero no está activo = a eliminar del carrito
        $removed = $requested->diff($activeIds)->values();

        $rates = app(ExchangeRate::class);
        $rate = $rates->current();

        $prices = $products->mapWithKeys(fn (Product $p) => [
            (string) $p->id => $rates->toBs($p->sale_price ?: $p->price, $rate),
        ]);

        return response()->json([
            'removed' => $removed,
            'prices' => $prices,
            'exchange_rate' => $rate,
        ]);
    }
}
