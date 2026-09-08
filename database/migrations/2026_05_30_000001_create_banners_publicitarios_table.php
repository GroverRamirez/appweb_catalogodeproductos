<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Banners del carrusel de la página principal, con vigencia opcional. */
    public function up(): void
    {
        Schema::create('banners_publicitarios', function (Blueprint $table) {
            $table->id();
            $table->string('titulo')->nullable();
            $table->string('subtitulo')->nullable();
            $table->string('imagen');
            $table->string('enlace')->nullable();
            $table->string('texto_cta', 50)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamp('inicia_en')->nullable();
            $table->timestamp('termina_en')->nullable();
            $table->timestamps();

            $table->index(['activo', 'orden'], 'banners_is_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners_publicitarios');
    }
};
