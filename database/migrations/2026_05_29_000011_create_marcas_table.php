<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Marcas/fabricantes asociados a los productos. */
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique('brands_slug_unique');
            $table->string('logo')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('sitio_web')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('activo', 'brands_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas');
    }
};
