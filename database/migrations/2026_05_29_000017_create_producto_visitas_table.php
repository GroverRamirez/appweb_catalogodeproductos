<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log de visitas a la ficha de cada producto. Es de solo inserción y se
     * purga a los 6 meses (ver ProductView::prunable()); el total acumulado
     * vive aparte en `productos.visitas`.
     */
    public function up(): void
    {
        Schema::create('producto_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id');
            $table->string('direccion_ip', 45)->nullable();
            $table->string('sesion_id', 100)->nullable();
            $table->string('agente_usuario', 500)->nullable();
            $table->string('referente', 500)->nullable();
            $table->timestamp('visto_en')->useCurrent();

            $table->index(['producto_id', 'visto_en'], 'product_views_product_id_viewed_at_index');
            $table->index('visto_en', 'product_views_viewed_at_index');
            $table->foreign('producto_id', 'product_views_product_id_foreign')
                ->references('id')->on('productos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_visitas');
    }
};
