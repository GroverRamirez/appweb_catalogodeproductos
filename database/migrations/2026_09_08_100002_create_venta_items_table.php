<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Líneas de una venta. Guarda snapshot de nombre/código del producto
     * (igual que `compra_items`) para que el historial sobreviva aunque el
     * producto cambie o se borre.
     */
    public function up(): void
    {
        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('producto_nombre_copia');
            $table->string('producto_codigo_copia', 50)->nullable();
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            // Costo congelado al momento de vender: el costo del producto
            // cambia con cada compra posterior, así que consultarlo más tarde
            // daría un margen falso. Es el único dato que permite calcular
            // ganancia real después.
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->timestamps();

            $table->index('venta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_items');
    }
};
