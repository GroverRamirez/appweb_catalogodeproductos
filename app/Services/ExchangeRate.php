<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tipo de cambio oficial del dolar (Bs por USD) bajo el regimen flexible.
 *
 * La lista de precios del catalogo se carga en dolares y la tienda cobra en
 * bolivianos: este servicio es el unico lugar que hace la conversion, para que
 * la vitrina, el carrito, la venta y los documentos no puedan usar cotizaciones
 * distintas.
 *
 * Cada consulta y cada venta guardan el tipo de cambio con el que se cerraron
 * (`tipo_cambio`), asi que un recibo viejo sigue diciendo lo mismo aunque el
 * BCB publique otra cotizacion al dia siguiente.
 */
class ExchangeRate
{
    /** Cotizacion de respaldo si nunca se cargo ninguna (BCB, 09/2026). */
    public const FALLBACK = 12.02;

    /** Rango razonable: descarta respuestas corruptas o mal parseadas. */
    private const MIN = 1.0;

    private const MAX = 100.0;

    /** Metadatos de las settings, para no perder etiqueta ni grupo al escribir. */
    private const KEYS = [
        'exchange_rate' => ['number', 'moneda', 'Tipo de cambio: bolivianos por dolar (lo actualiza el BCB a diario)'],
        'exchange_rate_source' => ['string', 'moneda', 'Origen de la ultima cotizacion (bcb o manual) — lo escribe el sistema'],
        'exchange_rate_updated_at' => ['string', 'moneda', 'Ultima actualizacion del tipo de cambio — lo escribe el sistema'],
    ];

    /** Cotizacion vigente. */
    public function current(): float
    {
        $value = (float) Setting::get('exchange_rate', self::FALLBACK);

        return $this->isSane($value) ? $value : self::FALLBACK;
    }

    /** 'bcb' si la trajo la tarea diaria, 'manual' si la fijo una persona. */
    public function source(): string
    {
        return (string) Setting::get('exchange_rate_source', 'manual');
    }

    /** Fecha ISO de la ultima actualizacion, o null si nunca se actualizo. */
    public function updatedAt(): ?string
    {
        $value = Setting::get('exchange_rate_updated_at');

        return $value ? (string) $value : null;
    }

    public function isSane(float $rate): bool
    {
        return $rate >= self::MIN && $rate <= self::MAX;
    }

    /**
     * Dolares a bolivianos. El `$rate` explicito es el congelado en un
     * documento: sin el, un recibo reimpreso el mes que viene mostraria otro
     * total.
     */
    public function toBs(float|int|string|null $usd, ?float $rate = null): float
    {
        return round(((float) $usd) * ($rate ?? $this->current()), 2);
    }

    /**
     * Bolivianos a dolares. Se usa para los filtros de precio del catalogo
     * (el cliente filtra en Bs, la base guarda USD) y para cargar costos
     * cotizados en bolivianos.
     */
    public function toUsd(float|int|string|null $bs, ?float $rate = null): float
    {
        $rate = $rate ?? $this->current();

        return $rate > 0 ? round(((float) $bs) / $rate, 2) : 0.0;
    }

    /** Guarda una cotizacion nueva con su origen y su fecha. */
    public function store(float $rate, string $source = 'manual', ?string $date = null): void
    {
        $this->put('exchange_rate', (string) $rate);
        $this->put('exchange_rate_source', $source);
        $this->put('exchange_rate_updated_at', $date ?? now()->toDateTimeString());
    }

    /**
     * Consulta la cotizacion oficial publicada por el BCB.
     *
     * Devuelve null ante cualquier problema —sin red, respuesta rara, valor
     * fuera de rango— en vez de lanzar: si la consulta falla, la tienda sigue
     * vendiendo con la ultima cotizacion conocida, que es preferible a una
     * vitrina caida o a precios en cero.
     *
     * @return array{rate: float, date: string|null}|null
     */
    public function fetch(): ?array
    {
        $url = (string) config('services.bcb.url');

        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout((int) config('services.bcb.timeout', 10))
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Tipo de cambio: el BCB respondio '.$response->status());

                return null;
            }

            $payload = $response->json('tc_oficial') ?? [];
            // `valor` es el TCO publicado; `compra` es el mismo numero desde la
            // RD BCB 142/2026, pero se deja como respaldo por si cambia.
            $rate = (float) ($payload['valor'] ?? $payload['compra'] ?? 0);

            if (! $this->isSane($rate)) {
                Log::warning('Tipo de cambio: valor fuera de rango', ['valor' => $rate]);

                return null;
            }

            return [
                'rate' => $rate,
                'date' => isset($payload['fecha']) ? (string) $payload['fecha'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Tipo de cambio: no se pudo consultar al BCB', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Escribe una setting sin pisar su etiqueta ni su grupo. */
    private function put(string $key, string $value): void
    {
        $setting = Setting::query()->where('clave', $key)->first();

        if ($setting) {
            $setting->update(['value' => $value]);

            return;
        }

        [$type, $group, $label] = self::KEYS[$key];

        Setting::set($key, $value, $type, $group, $label);
    }
}
