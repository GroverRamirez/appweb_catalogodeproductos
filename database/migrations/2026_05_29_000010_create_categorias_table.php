<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categorías del catálogo. Se auto-referencian para armar subcategorías.
     *
     * Los índices conservan su nombre original en inglés a propósito: son los
     * que ya existen en las bases instaladas, y renombrarlos obligaría a
     * borrar y recrear las claves foráneas sin ganar nada funcional.
     */
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique('categories_slug_unique');
            $table->text('descripcion')->nullable();
            $table->foreignId('categoria_padre_id')->nullable();
            $table->string('imagen')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'orden'], 'categories_is_active_sort_order_index');
            $table->foreign('categoria_padre_id', 'categories_parent_id_foreign')
                ->references('id')->on('categorias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
