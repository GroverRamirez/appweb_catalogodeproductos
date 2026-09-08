<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enlaza ventas y consultas con el cliente.
     *
     * Nullable a propósito: la venta al paso ("consumidor final") no necesita
     * cliente, y una consulta vieja puede no tener teléfono con el que
     * identificarlo.
     *
     * Los snapshots `cliente_nombre`/`cliente_telefono` que ya tienen ambas
     * tablas NO se quitan: mismo criterio que `producto_nombre_copia`. La FK
     * sirve para agrupar e ir al historial; el snapshot, para que el
     * comprobante emitido siga diciendo lo que decía aunque el cliente cambie
     * de teléfono después.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('consulta_id')
                ->constrained('clientes')->nullOnDelete();
        });

        Schema::table('consultas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('token_publico')
                ->constrained('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });

        Schema::table('consultas', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });
    }
};
