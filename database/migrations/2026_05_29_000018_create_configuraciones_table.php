<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Configuración de la tienda en pares clave/valor (nombre, logo, moneda...). */
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave');
            $table->longText('valor')->nullable();
            $table->string('grupo', 50)->default('general');
            $table->string('etiqueta')->nullable();
            $table->string('tipo', 20)->default('string')->comment('string, text, json, boolean, image, number');
            $table->timestamps();

            $table->unique('clave', 'settings_key_unique');
            $table->index('grupo', 'settings_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
