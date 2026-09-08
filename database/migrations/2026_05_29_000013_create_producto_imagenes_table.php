<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Galería de imágenes de cada producto. `ruta_thumb` guarda la miniatura. */
    public function up(): void
    {
        Schema::create('producto_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id');
            $table->string('ruta');
            $table->string('ruta_thumb')->nullable();
            $table->string('texto_alternativo')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->index(['producto_id', 'orden'], 'product_images_product_id_sort_order_index');
            $table->foreign('producto_id', 'product_images_product_id_foreign')
                ->references('id')->on('productos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagenes');
    }
};
