<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Setting;
use App\Services\DatabaseBackup;
use App\Services\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pasa a dolares las compras que se registraron cuando todo estaba en
 * bolivianos.
 *
 * `precios:convertir-a-usd` dividio la lista de productos pero no tocaba
 * `compras` ni `compra_items`: esas compras viejas guardan costos en Bs y el
 * panel las muestra como USD (unas 12 veces mas caras). Este comando divide
 * `costo_unitario`, `costo_anterior` y recalcula `costo_total` de las compras
 * creadas antes de `--antes`, y deja una marca que impide repetirlo.
 */
class ConvertPurchasesToUsd extends Command
{
    protected $signature = 'compras:convertir-a-usd
                            {--antes= : Solo compras creadas antes de esta fecha (AAAA-MM-DD), la de la conversion de la lista}
                            {--tc= : Cotizacion a usar (por defecto, la vigente)}
                            {--dry-run : Muestra que pasaria, sin escribir}
                            {--sin-backup : No genera la copia de la base antes de escribir}
                            {--si : No pide confirmacion}
                            {--force : Convierte aunque las compras ya figuren en dolares}';

    protected $description = 'Convierte a dolares los costos de las compras registradas en bolivianos';

    public function handle(ExchangeRate $rates, DatabaseBackup $backup): int
    {
        $rate = (float) ($this->option('tc') ?: $rates->current());

        if (! $rates->isSane($rate)) {
            $this->error("La cotizacion {$rate} no parece valida.");

            return self::FAILURE;
        }

        if (Setting::get('purchases_currency') === 'USD' && ! $this->option('force')) {
            $this->warn('Las compras ya figuran en dolares. Usa --force solo si estas seguro.');

            return self::FAILURE;
        }

        $before = (string) $this->option('antes');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
            $this->error('Indica con --antes=AAAA-MM-DD la fecha en que la lista paso a dolares: las compras posteriores ya estan en USD.');

            return self::FAILURE;
        }

        $purchases = DB::table('compras')
            ->where('created_at', '<', $before.' 00:00:00')
            ->orderBy('id')
            ->get(['id', 'numero_referencia', 'estado', 'costo_total', 'created_at']);

        if ($purchases->isEmpty()) {
            $this->info("No hay compras anteriores al {$before}.");

            return self::SUCCESS;
        }

        $ids = $purchases->pluck('id');
        $items = DB::table('compra_items')->whereIn('compra_id', $ids)->get(['id', 'compra_id', 'cantidad', 'costo_unitario', 'costo_anterior']);

        // El total se recalcula desde las lineas ya redondeadas, para que
        // detalle y total sigan cuadrando despues de dividir.
        $newTotals = $items->groupBy('compra_id')->map(fn ($rows) => round($rows->sum(
            fn ($i) => (int) $i->cantidad * round(((float) $i->costo_unitario) / $rate, 2)
        ), 2));

        $this->info("Cotizacion usada: {$rate} Bs por USD");
        $this->table(
            ['Compra', 'Fecha', 'Estado', 'Total Bs (hoy dice USD)', 'Total USD'],
            $purchases->map(fn ($p) => [
                '#'.$p->id,
                substr((string) $p->created_at, 0, 10),
                $p->estado,
                $p->costo_total,
                number_format($newTotals[$p->id] ?? 0, 2, '.', ''),
            ])->all(),
        );

        $summary = "{$purchases->count()} compras y {$items->count()} lineas";

        if ($this->option('dry-run')) {
            $this->comment("Simulacion: se convertirian {$summary}. Nada fue modificado.");

            return self::SUCCESS;
        }

        if (! $this->option('si') && ! $this->confirm("Se van a convertir {$summary}. Continuar?")) {
            $this->comment('Cancelado.');

            return self::FAILURE;
        }

        if (! $this->option('sin-backup')) {
            try {
                $this->info('Copia de seguridad: '.$backup->create());
            } catch (Throwable $e) {
                $this->error('No se pudo hacer la copia de seguridad: '.$e->getMessage());
                $this->comment('Haz una copia a mano y vuelve a correrlo con --sin-backup.');

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($ids, $rate, $newTotals) {
            DB::table('compra_items')->whereIn('compra_id', $ids)->update([
                'costo_unitario' => DB::raw("ROUND(costo_unitario / {$rate}, 2)"),
                'costo_anterior' => DB::raw("CASE WHEN costo_anterior IS NULL THEN NULL ELSE ROUND(costo_anterior / {$rate}, 2) END"),
            ]);

            foreach ($ids as $id) {
                DB::table('compras')->where('id', $id)->update(['costo_total' => $newTotals[$id] ?? 0]);
            }

            Setting::set('purchases_currency', 'USD', 'string', 'moneda', 'Moneda de las compras — la escribe el sistema');
            // Si las compras se pasan a dolares es porque la lista ya lo esta;
            // la marca de la lista pudo haber quedado en BOB si la migracion
            // del tipo de cambio corrio despues de convertirla.
            Setting::set('price_list_currency', 'USD', 'string', 'moneda', 'Moneda de la lista de precios — la escribe el sistema');
        });

        DashboardController::flushCache();

        $this->info("Listo: {$summary} quedaron en dolares.");

        return self::SUCCESS;
    }
}
