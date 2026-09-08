<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda el costo que tenía el producto justo antes de que esta línea lo
     * pisara. Sin este dato, anular una compra devolvía el stock pero dejaba
     * el costo de la compra anulada, sin forma de recuperar el anterior.
     *
     * Queda NULL cuando la línea no tocó el costo (ingresos con costo 0, que
     * nunca sobrescriben) o cuando el producto no tenía costo previo.
     */
    public function up(): void
    {
        Schema::table('compra_items', function (Blueprint $table) {
            $table->decimal('costo_anterior', 12, 2)->nullable()->after('costo_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('compra_items', function (Blueprint $table) {
            $table->dropColumn('costo_anterior');
        });
    }
};
