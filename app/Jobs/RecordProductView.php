<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Registra una visita de producto de forma asíncrona.
 *
 * El response del catálogo se devuelve de inmediato; el worker procesa
 * el INSERT en producto_visitas y el INCREMENT de visitas en segundo plano.
 *
 * La deduplicación (evitar contar dos veces la misma sesión) se hace en el
 * controller ANTES de despachar el job — la clave de sesión se escribe de
 * forma síncrona para que múltiples requests rápidos no disparen dos jobs.
 *
 * Pasamos únicamente valores escalares al constructor para evitar cualquier
 * problema de serialización con modelos Eloquent.
 *
 * En tests (QUEUE_CONNECTION=sync) el job se ejecuta de forma síncrona e
 * inmediata, por lo que las assertions sobre ProductView y visitas siguen
 * funcionando sin cambios.
 */
class RecordProductView implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Un único reintento: si el INSERT falla por una falla transitoria de la DB
     * se reintenta una vez. Perder una vista no es crítico, así que no acumulamos
     * más intentos en failed_jobs.
     */
    public int $tries = 2;

    /** Segundos entre el intento 1 y el reintento. */
    public int $backoff = 5;

    /** Máximo de segundos antes de que el worker considere el job colgado. */
    public int $timeout = 30;

    public function __construct(
        public readonly int $productId,
        public readonly string $ipAddress,
        public readonly string $sessionId,
        public readonly string $userAgent,
        public readonly string $referrer,
    ) {}

    public function handle(): void
    {
        // El producto puede haber sido eliminado entre el dispatch y el procesamiento.
        $product = Product::find($this->productId);

        if (! $product) {
            return;
        }

        ProductView::create([
            'product_id' => $product->id,
            'ip_address' => $this->ipAddress,
            'session_id' => $this->sessionId,
            'user_agent' => $this->userAgent,
            'referrer' => $this->referrer,
            'viewed_at' => now(),
        ]);

        // Contador denormalizado para evitar COUNT(*) en queries frecuentes.
        $product->increment('visitas');
    }
}
