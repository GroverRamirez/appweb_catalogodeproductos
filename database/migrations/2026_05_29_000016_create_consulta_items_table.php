<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Líneas de cada consulta. Guarda copia del nombre y código del producto
     * para que el pedido siga siendo legible aunque el producto cambie o se
     * elimine del catálogo.
     */
    public function up(): void
    {
        Schema::create('consulta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id');
            $table->foreignId('producto_id')->nullable();
            $table->string('producto_nombre_copia')->comment('Copia del nombre por si el producto cambia');
            $table->string('producto_codigo_copia', 50)->nullable();
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->timestamps();

            $table->index('consulta_id', 'inquiry_items_inquiry_id_index');
            $table->foreign('consulta_id', 'inquiry_items_inquiry_id_foreign')
                ->references('id')->on('consultas')->cascadeOnDelete();
            $table->foreign('producto_id', 'inquiry_items_product_id_foreign')
                ->references('id')->on('productos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_items');
    }
};
