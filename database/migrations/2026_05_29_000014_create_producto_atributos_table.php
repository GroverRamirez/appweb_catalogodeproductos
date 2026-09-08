<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Características libres del producto, en pares clave/valor. */
    public function up(): void
    {
        Schema::create('producto_atributos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id');
            $table->string('clave');
            $table->string('valor');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['producto_id', 'orden'], 'product_attributes_product_id_sort_order_index');
            $table->foreign('producto_id', 'product_attributes_product_id_foreign')
                ->references('id')->on('productos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_atributos');
    }
};
