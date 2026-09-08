<?php

use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('el detalle de visitas viejo se purga y el reciente se conserva', function () {
    $product = Product::factory()->create(['visitas' => 500]);

    $vieja = ProductView::create([
        'product_id' => $product->id,
        'viewed_at' => now()->subMonths(ProductView::RETENTION_MONTHS + 1),
    ]);
    $reciente = ProductView::create([
        'product_id' => $product->id,
        'viewed_at' => now()->subDays(3),
    ]);
    // Justo en el borde de la ventana: se conserva.
    $borde = ProductView::create([
        'product_id' => $product->id,
        'viewed_at' => now()->subMonths(ProductView::RETENTION_MONTHS)->addDay(),
    ]);

    $this->artisan('model:prune', ['--model' => [ProductView::class]])
        ->assertSuccessful();

    expect(ProductView::find($vieja->id))->toBeNull();
    expect(ProductView::find($reciente->id))->not->toBeNull();
    expect(ProductView::find($borde->id))->not->toBeNull();
});

test('purgar el detalle no toca el contador acumulado del producto', function () {
    // El total de vistas de un producto vive en productos.visitas, no se
    // recalcula desde el log: purgar el detalle no puede alterarlo.
    $product = Product::factory()->create(['visitas' => 500]);

    ProductView::create([
        'product_id' => $product->id,
        'viewed_at' => now()->subYear(),
    ]);

    $this->artisan('model:prune', ['--model' => [ProductView::class]]);

    expect($product->fresh()->views_count)->toBe(500);
    expect(ProductView::count())->toBe(0);
});
