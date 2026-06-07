<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garantiza que el índice FULLTEXT exista en las columnas en español
 * (nombre, descripcion_corta, descripcion) de la tabla productos.
 *
 * La migración original creó el índice sobre las columnas en inglés.
 * MySQL 8.0 actualiza las referencias al renombrar columnas, pero esta
 * migración actúa como salvaguarda y recrea el índice si no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return; // SQLite no soporta FULLTEXT
        }

        if (! Schema::hasTable('productos')) {
            return;
        }

        // Laravel 11+ expone Schema::getIndexes() que devuelve un array con shape:
        // [['name' => '...', 'columns' => [...], 'type' => 'fulltext', ...]]
        $hasFulltext = collect(Schema::getIndexes('productos'))
            ->contains(fn ($idx) => ($idx['type'] ?? '') === 'fulltext');

        if (! $hasFulltext) {
            Schema::table('productos', function (Blueprint $table) {
                $table->fullText(['nombre', 'descripcion_corta', 'descripcion'], 'productos_fulltext');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('productos')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropFullText('productos_fulltext');
            });
        }
    }
};
