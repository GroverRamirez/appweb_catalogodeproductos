<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca si la consulta tiene tomado un uso del cupón.
     *
     * El uso se consume en el checkout, cuando todavía no se sabe si el pedido
     * va a terminar en venta, y hasta ahora no se devolvía nunca: un cupón con
     * tope de usos se agotaba con consultas que se cerraron sin vender o que se
     * eliminaron.
     *
     * La bandera es lo que hace idempotente la devolución. Sin ella, cerrar dos
     * veces la misma consulta —o cerrarla y después eliminarla— devolvería el
     * uso dos veces, que es peor que no devolverlo.
     *
     * Backfill: toda consulta con código de cupón lo tomó en su momento, así
     * que arranca en true.
     */
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->boolean('cupon_consumido')->default(false)->after('cupon_codigo');
        });

        DB::table('consultas')
            ->whereNotNull('cupon_codigo')
            ->where('cupon_codigo', '!=', '')
            ->update(['cupon_consumido' => true]);
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('cupon_consumido');
        });
    }
};
