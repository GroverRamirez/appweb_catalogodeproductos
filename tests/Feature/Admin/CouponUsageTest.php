<?php

use App\Models\Coupon;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');

    $this->product = Product::factory()->create(['stock' => 50, 'precio' => 100]);

    $this->coupon = Coupon::create([
        'code' => 'VERANO10',
        'type' => 'percent',
        'value' => 10,
        'max_uses' => 1,
        'is_active' => true,
    ]);
});

/** Hace el checkout del catálogo con el cupón puesto. */
function checkoutConCupon(int $productId, string $code = 'VERANO10'): void
{
    test()->post(route('public.checkout'), [
        'customer_name' => 'Ana',
        'customer_phone' => '70000000',
        'coupon_code' => $code,
        'items' => [['product_id' => $productId, 'quantity' => 1]],
    ]);
}

test('el checkout toma un uso del cupon', function () {
    checkoutConCupon($this->product->id);

    expect($this->coupon->fresh()->used_count)->toBe(1);

    $inquiry = Inquiry::firstOrFail();
    expect($inquiry->coupon_code)->toBe('VERANO10');
    expect($inquiry->coupon_consumed)->toBeTrue();
});

test('cerrar la consulta sin vender devuelve el uso', function () {
    checkoutConCupon($this->product->id);
    $inquiry = Inquiry::firstOrFail();

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'cerrado']);

    expect($this->coupon->fresh()->used_count)->toBe(0);
    expect($inquiry->fresh()->coupon_consumed)->toBeFalse();

    // Y el cupón vuelve a servir: era un cupón de un solo uso.
    expect($this->coupon->fresh()->isUsable(100.0))->toBeTrue();
});

test('el descuento prometido sobrevive a la devolucion del uso', function () {
    checkoutConCupon($this->product->id);
    $inquiry = Inquiry::firstOrFail();

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'cerrado']);

    $fresh = $inquiry->fresh();
    expect($fresh->coupon_code)->toBe('VERANO10');
    expect((float) $fresh->discount_amount)->toBe(10.0);
});

test('cerrar dos veces devuelve un solo uso', function () {
    checkoutConCupon($this->product->id);
    $inquiry = Inquiry::firstOrFail();

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'cerrado']);
    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'contactado']);
    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'cerrado']);

    // Nunca por debajo de cero, y nunca dos devoluciones por un solo uso.
    expect($this->coupon->fresh()->used_count)->toBe(0);
});

test('eliminar la consulta devuelve el uso', function () {
    checkoutConCupon($this->product->id);
    $inquiry = Inquiry::firstOrFail();

    $this->actingAs($this->owner)->delete(route('admin.inquiries.destroy', $inquiry));

    expect($this->coupon->fresh()->used_count)->toBe(0);
});

test('vender la consulta conserva el uso tomado', function () {
    checkoutConCupon($this->product->id);
    $inquiry = Inquiry::firstOrFail();

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    // Se vendió con el cupón: el uso es legítimo y se queda gastado.
    expect($this->coupon->fresh()->used_count)->toBe(1);
    expect($inquiry->fresh()->coupon_consumed)->toBeTrue();
});

test('una consulta sin cupon no toca ningun contador al cerrarse', function () {
    $this->post(route('public.checkout'), [
        'customer_name' => 'Sin cupón',
        'customer_phone' => '70000001',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
    ]);

    $inquiry = Inquiry::firstOrFail();
    expect($inquiry->coupon_consumed)->toBeFalse();

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'cerrado']);

    expect($this->coupon->fresh()->used_count)->toBe(0);
});

test('el tope del cupon se respeta mientras el uso sigue tomado', function () {
    checkoutConCupon($this->product->id);

    // Segundo pedido con el mismo cupón de un solo uso: no debe descontar.
    $this->post(route('public.checkout'), [
        'customer_name' => 'Otro',
        'customer_phone' => '70000002',
        'coupon_code' => 'VERANO10',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
    ]);

    expect($this->coupon->fresh()->used_count)->toBe(1);

    $segunda = Inquiry::where('cliente_telefono', '70000002')->firstOrFail();
    expect($segunda->coupon_code)->toBeNull();
    expect($segunda->coupon_consumed)->toBeFalse();
});
