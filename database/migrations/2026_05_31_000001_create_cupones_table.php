<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cupones de descuento aplicables al carrito de consulta. */
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40);
            $table->string('descripcion')->nullable();
            $table->enum('tipo', ['percent', 'fixed'])->default('percent');
            $table->decimal('valor', 12, 2)->default(0);
            $table->decimal('subtotal_minimo', 12, 2)->nullable();
            $table->unsignedInteger('usos_maximos')->nullable();
            $table->unsignedInteger('usos_realizados')->default(0);
            $table->timestamp('inicia_en')->nullable();
            $table->timestamp('termina_en')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique('codigo', 'coupons_code_unique');
            $table->index(['activo', 'codigo'], 'coupons_is_active_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};
