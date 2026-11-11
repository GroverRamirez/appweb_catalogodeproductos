<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kardex: toda entrada y salida de stock queda registrada acá.
     *
     * Hasta ahora `productos.stock` era un número suelto. Las compras y las
     * ventas lo movían dejando su documento, pero una corrección escrita a mano
     * en el formulario de producto no dejaba ninguno, así que el stock no se
     * podía reconciliar contra nada.
     *
     * `productos.stock` se conserva como total rápido —el catálogo y los
     * listados lo leen en cada request y no pueden andar sumando el historial—,
     * pero deja de ser la única verdad: ahora es la suma de esta tabla, y
     * `InventoryLedger` escribe las dos cosas juntas para que no diverjan.
     *
     * `stock_anterior` y `stock_resultante` se guardan en cada fila para poder
     * leer el saldo corrido sin recalcular toda la historia del producto.
     */
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();

            // nullOnDelete + copias del nombre y el código, igual que las líneas
            // de venta y compra: el kardex tiene que sobrevivir al producto.
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('producto_nombre_copia');
            $table->string('producto_codigo_copia', 50)->nullable();

            $table->string('tipo', 30)->comment('compra, venta, anulacion_compra, anulacion_venta, ajuste');
            // Con signo: positivo entra, negativo sale.
            $table->integer('cantidad');
            $table->integer('stock_anterior');
            $table->integer('stock_resultante');

            // Documento que originó el movimiento (venta o compra). El ajuste
            // manual no tiene documento: su justificación es el motivo.
            $table->nullableMorphs('origen');
            $table->string('motivo')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['producto_id', 'id'], 'inventory_movements_product_id_id_index');
            $table->index('tipo', 'inventory_movements_type_index');
            $table->index('created_at', 'inventory_movements_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
