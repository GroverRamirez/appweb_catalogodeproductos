<?php

use App\Services\ExchangeRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte de doble moneda: lista de precios en dolares, cobro en bolivianos.
 *
 * Cada consulta y cada venta guardan la cotizacion con la que se cerraron. Sin
 * esa columna, reabrir una venta vieja la revalorizaria al tipo de cambio de
 * hoy y el recibo dejaria de coincidir con lo que el cliente pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->decimal('tipo_cambio', 10, 4)->nullable();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('tipo_cambio', 10, 4)->nullable();
        });

        $now = now();

        $settings = [
            ['clave' => 'exchange_rate', 'valor' => (string) ExchangeRate::FALLBACK, 'tipo' => 'number', 'grupo' => 'moneda', 'etiqueta' => 'Tipo de cambio: bolivianos por dolar (lo actualiza el BCB a diario)'],
            ['clave' => 'exchange_rate_source', 'valor' => 'manual', 'tipo' => 'string', 'grupo' => 'moneda', 'etiqueta' => 'Origen de la ultima cotizacion (bcb o manual) — lo escribe el sistema'],
            ['clave' => 'exchange_rate_updated_at', 'valor' => '', 'tipo' => 'string', 'grupo' => 'moneda', 'etiqueta' => 'Ultima actualizacion del tipo de cambio — lo escribe el sistema'],
            // Una instalacion que ya venia funcionando tiene la lista cargada
            // en bolivianos; `precios:convertir-a-usd` la pasa a dolares y
            // cambia esta marca, que evita convertirla dos veces.
            ['clave' => 'price_list_currency', 'valor' => 'BOB', 'tipo' => 'string', 'grupo' => 'moneda', 'etiqueta' => 'Moneda de la lista de precios — la escribe el sistema'],
        ];

        foreach ($settings as $row) {
            // Solo se agrega si falta: la instalacion nueva ya las trae del
            // seeder, y esta migracion no debe pisarle el valor cargado
            // (updateOrInsert lo pisaba y devolvia la marca de la lista a BOB).
            if (DB::table('configuraciones')->where('clave', $row['clave'])->exists()) {
                continue;
            }

            DB::table('configuraciones')->insert(array_merge($row, ['created_at' => $now, 'updated_at' => $now]));
        }
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn('tipo_cambio');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('tipo_cambio');
        });

        DB::table('configuraciones')
            ->whereIn('clave', ['exchange_rate', 'exchange_rate_source', 'exchange_rate_updated_at', 'price_list_currency'])
            ->delete();
    }
};
