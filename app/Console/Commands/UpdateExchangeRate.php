<?php

namespace App\Console\Commands;

use App\Services\ExchangeRate;
use Illuminate\Console\Command;

/**
 * Actualiza la cotizacion oficial del dolar.
 *
 * Corre todos los dias despues de la publicacion del BCB (23:30 en dia habil,
 * rige al dia siguiente) y tambien a mano, con `--valor`, cuando el encargado
 * necesita fijar otra cotizacion.
 */
class UpdateExchangeRate extends Command
{
    protected $signature = 'tipo-cambio:actualizar
                            {--valor= : Fija la cotizacion a mano en vez de consultar al BCB}';

    protected $description = 'Actualiza el tipo de cambio oficial del dolar (bolivianos por USD)';

    public function handle(ExchangeRate $rates): int
    {
        if ($manual = $this->option('valor')) {
            $rate = (float) str_replace(',', '.', (string) $manual);

            if (! $rates->isSane($rate)) {
                $this->error("El valor {$manual} no parece un tipo de cambio valido.");

                return self::FAILURE;
            }

            $rates->store($rate, 'manual');
            $this->info("Tipo de cambio fijado a mano en {$rate} Bs por USD.");

            return self::SUCCESS;
        }

        $result = $rates->fetch();

        if ($result === null) {
            // Falla blanda: la tienda sigue con la ultima cotizacion conocida.
            $this->warn(
                'No se pudo obtener la cotizacion del BCB. Sigue vigente '
                .$rates->current().' Bs por USD.'
            );

            return self::FAILURE;
        }

        $rates->store($result['rate'], 'bcb', $result['date']);
        $this->info("Tipo de cambio actualizado a {$result['rate']} Bs por USD ({$result['date']}).");

        return self::SUCCESS;
    }
}
