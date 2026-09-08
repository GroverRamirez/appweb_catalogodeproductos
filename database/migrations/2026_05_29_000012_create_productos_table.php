<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos del catálogo. Incluye el índice FULLTEXT que usa la búsqueda
     * pública en MySQL (en SQLite, los tests caen al fallback con LIKE).
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->comment('SKU / código interno');
            $table->string('nombre');
            $table->string('slug');
            $table->text('descripcion_corta')->nullable();
            $table->longText('descripcion')->nullable();
            $table->decimal('precio', 12, 2)->default(0);
            $table->decimal('precio_oferta', 12, 2)->nullable()->comment('Precio promocional opcional');
            $table->decimal('costo', 12, 2)->nullable()->comment('Costo interno (no se muestra al público)');
            $table->integer('stock')->default(0);
            $table->unsignedInteger('stock_minimo')->default(0)->comment('Umbral para alerta de stock bajo');
            $table->string('unidad', 20)->default('unidad')->comment('unidad, kg, caja, etc.');
            $table->foreignId('categoria_id')->nullable();
            $table->foreignId('marca_id')->nullable();
            $table->boolean('destacado')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('visitas')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('codigo', 'products_code_unique');
            $table->unique('slug', 'products_slug_unique');
            $table->index(['activo', 'destacado'], 'products_is_active_is_featured_index');
            $table->index(['categoria_id', 'activo'], 'products_category_id_is_active_index');
            $table->index(['marca_id', 'activo'], 'products_brand_id_is_active_index');

            $table->foreign('categoria_id', 'products_category_id_foreign')
                ->references('id')->on('categorias')->nullOnDelete();
            $table->foreign('marca_id', 'products_brand_id_foreign')
                ->references('id')->on('marcas')->nullOnDelete();
        });

        // FULLTEXT solo existe en MySQL/MariaDB; en SQLite (tests) se omite.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('productos', function (Blueprint $table) {
                $table->fullText(
                    ['nombre', 'descripcion_corta', 'descripcion'],
                    'products_name_short_description_description_fulltext'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
