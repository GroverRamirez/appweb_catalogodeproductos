<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductCostsRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carga masiva del costo de los productos. Sin costo no se puede valorizar el
 * inventario ni calcular margen, y cargarlos uno por uno desde el formulario
 * de producto es inviable cuando faltan decenas.
 */
class ProductCostController extends Controller
{
    public function edit(Request $request): Response
    {
        // Por defecto solo los que faltan; ?all=1 permite revisar y corregir
        // también los que ya tienen costo cargado.
        $showAll = $request->boolean('all');

        $products = Product::query()
            ->when(! $showAll, fn ($q) => $q->withoutCost())
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'precio', 'precio_oferta', 'costo', 'stock']);

        return Inertia::render('admin/products/Costs', [
            'products' => $products,
            'showAll' => $showAll,
            'missingCount' => Product::withoutCost()->count(),
            'totalCount' => Product::count(),
        ]);
    }

    public function update(UpdateProductCostsRequest $request): RedirectResponse
    {
        // Solo las filas con un valor cargado: una fila en blanco significa
        // "sigo sin saberlo", no "borrá el costo".
        $rows = collect($request->validated('items'))
            ->filter(fn (array $row) => isset($row['cost']) && $row['cost'] !== '');

        if ($rows->isEmpty()) {
            return back()->with('error', 'No cargaste ningún costo.');
        }

        $updated = DB::transaction(function () use ($rows) {
            $products = Product::query()
                ->whereIn('id', $rows->pluck('id'))
                ->get()
                ->keyBy('id');

            $count = 0;

            foreach ($rows as $row) {
                $product = $products->get((int) $row['id']);

                if (! $product) {
                    continue;
                }

                // Guardar por modelo (y no con un update masivo) para que se
                // dispare el evento que invalida el cache del panel.
                $product->update(['cost' => (float) $row['cost']]);
                $count++;
            }

            return $count;
        });

        return back()->with('success', "Se actualizó el costo de {$updated} productos.");
    }
}
