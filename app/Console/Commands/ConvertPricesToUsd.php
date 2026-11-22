<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa la lista de precios de bolivianos a dolares, una sola vez.
 *
 * El catalogo nacio con los precios cargados en bolivianos. Desde que la
 * vitrina convierte al tipo de cambio del dia, `productos.precio`,
 * `precio_oferta` y `costo` tienen que estar en dolares; este comando hace esa
 * division con la cotizacion que se le indique, y pasa tambien las compras
 * registradas hasta ese momento (ver `compras:convertir-a-usd`).
 *
 * Se protege contra correrlo dos veces: dividir la lista de nuevo dejaria todo
 * a una doceava parte de su valor.
 */
class ConvertPricesToUsd extends Command
{
    protected $signature = 'precios:convertir-a-usd
                            {--tc= : Cotizacion a usar (por defecto, la vigente)}
                            {--dry-run : Muestra que pasaria, sin escribir}
                            {--force : Convierte aunque la lista ya figure en dolares}';

    protected $description = 'Convierte la lista de precios de bolivianos a dolares dividiendo por el tipo de cambio';

    public function handle(ExchangeRate $rates): int
    {
        $rate = (float) ($this->option('tc') ?: $rates->current());

        if (! $rates->isSane($rate)) {
            $this->error("La cotizacion {$rate} no parece valida.");

            return self::FAILURE;
        }

        $currency = (string) Setting::get('price_list_currency', 'BOB');

        if ($currency === 'USD' && ! $this->option('force')) {
            $this->warn('La lista ya figura en dolares. Usa --force solo si estas seguro.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $sample = DB::table('productos')
            ->select('codigo', 'nombre', 'precio', 'precio_oferta', 'costo')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $this->info("Cotizacion usada: {$rate} Bs por USD");
        $this->table(
            ['Codigo', 'Precio Bs', 'Precio USD', 'Costo Bs', 'Costo USD'],
            $sample->map(fn ($p) => [
                $p->codigo,
                $p->precio,
                round(((float) $p->precio) / $rate, 2),
                $p->costo,
                $p->costo !== null ? round(((float) $p->costo) / $rate, 2) : '—',
            ])->all(),
        );

        $total = DB::table('productos')->count();

        if ($dryRun) {
            $this->comment("Simulacion: se convertirian {$total} productos. Nada fue modificado.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Se van a convertir {$total} productos. Hiciste backup de la base?")) {
            $this->comment('Cancelado.');

            return self::FAILURE;
        }

        // Una sola sentencia por columna: son miles de filas y no hace falta
        // traerlas al proceso para dividirlas.
        DB::table('productos')->update([
            'precio' => DB::raw("ROUND(precio / {$rate}, 2)"),
            'precio_oferta' => DB::raw("CASE WHEN precio_oferta IS NULL THEN NULL ELSE ROUND(precio_oferta / {$rate}, 2) END"),
            'costo' => DB::raw("CASE WHEN costo IS NULL THEN NULL ELSE ROUND(costo / {$rate}, 2) END"),
        ]);

        Setting::set('price_list_currency', 'USD', 'string', 'moneda', 'Moneda de la lista de precios — la escribe el sistema');

        $this->info("Listo: {$total} productos quedaron con su precio en dolares.");

        // Las compras registradas hasta hoy tambien estan en bolivianos y
        // alimentan el costo: si quedaran asi, el panel las mostraria como USD.
        $this->call('compras:convertir-a-usd', [
            '--antes' => now()->addDay()->toDateString(),
            '--tc' => (string) $rate,
            '--sin-backup' => true,
            '--si' => true,
        ]);
        $this->comment('Revisa un par de fichas en el panel antes de publicar.');

        return self::SUCCESS;
    }
}
