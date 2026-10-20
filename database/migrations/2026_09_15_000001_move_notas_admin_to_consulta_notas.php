<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifica las notas de una consulta en un solo mecanismo.
     *
     * Había dos conviviendo: `consultas.notas_admin` —un bloque de texto sin
     * autor ni fecha, del diseño original— y la tabla `consulta_notas`, que
     * guarda cada nota con quién la escribió y cuándo. El formulario escribía
     * la columna, el historial escribía la tabla, y el CSV exportaba solo la
     * columna: una nota cargada en el historial nunca salía en el export.
     *
     * Gana la tabla, que es la que tiene autor y fecha. Lo cargado en la
     * columna no se pierde: pasa al historial como una nota sin autor, fechada
     * con la creación de la consulta para no inventar un momento que no fue.
     */
    public function up(): void
    {
        DB::table('consultas')
            ->select('id', 'notas_admin', 'created_at')
            ->whereNotNull('notas_admin')
            ->where('notas_admin', '!=', '')
            ->orderBy('id')
            ->chunk(200, function ($consultas) {
                $ahora = now();

                foreach ($consultas as $consulta) {
                    DB::table('consulta_notas')->insert([
                        'consulta_id' => $consulta->id,
                        'user_id' => null,
                        'cuerpo' => $consulta->notas_admin,
                        'created_at' => $consulta->created_at ?? $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            });

        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('notas_admin');
        });
    }

    /**
     * Devuelve la columna, pero vacía: el contenido ya vive en el historial y
     * copiarlo de vuelta duplicaría cada nota en las dos partes, que es
     * justamente lo que esta migración vino a terminar.
     */
    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->text('notas_admin')->nullable()->after('descuento_monto');
        });
    }
};
