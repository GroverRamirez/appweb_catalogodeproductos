<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_imagenes', function (Blueprint $table) {
            // Ruta del thumbnail WebP generado automáticamente al subir la imagen.
            // NULL para imágenes migradas antes de esta columna (compatibilidad).
            $table->string('ruta_thumb')->nullable()->after('ruta');
        });
    }

    public function down(): void
    {
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->dropColumn('ruta_thumb');
        });
    }
};
