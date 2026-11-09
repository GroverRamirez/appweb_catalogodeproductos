<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Notifications\ExchangeRateRejectedNotification;
use App\Services\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        if ($rates->exceedsMaxChange($result['rate'])) {
            $current = $rates->current();

            // Se conserva la cotizacion vigente: la tarea vuelve a intentar en
            // la siguiente vuelta, y si el salto es real el encargado lo fija a
            // mano con --valor o desde Configuracion.
            $this->error(
                "Cotizacion rechazada: {$result['rate']} Bs por USD se aleja demasiado de la vigente ({$current})."
            );
            Log::warning('Tipo de cambio: variacion fuera de lo esperado, se conserva la vigente', [
                'vigente' => $current,
                'recibida' => $result['rate'],
                'fecha' => $result['date'],
            ]);
            $this->notifyRejection($current, $result['rate']);

            return self::FAILURE;
        }

        $rates->store($result['rate'], 'bcb', $result['date']);
        $this->info("Tipo de cambio actualizado a {$result['rate']} Bs por USD ({$result['date']}).");

        return self::SUCCESS;
    }

    /**
     * Avisa por email al contacto de la tienda, una sola vez por dia: la tarea
     * reintenta cada 30 minutos y no tiene sentido llenar la bandeja.
     */
    private function notifyRejection(float $current, float $received): void
    {
        $email = Setting::get('email_contact');

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (! Cache::add('tipo-cambio:rechazo-avisado:'.now()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new ExchangeRateRejectedNotification($current, $received));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
