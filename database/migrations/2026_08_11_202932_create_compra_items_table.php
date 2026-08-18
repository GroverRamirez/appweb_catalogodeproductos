<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Líneas de una compra. Guarda snapshot de nombre/código del producto
     * (igual que `consulta_items`) para que el historial sobreviva aunque el
     * producto cambie o se borre.
     */
    public function up(): void
    {
        Schema::create('compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('producto_nombre_copia');
            $table->string('producto_codigo_copia', 50)->nullable();
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->timestamps();

            $table->index('compra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_items');
    }
};
