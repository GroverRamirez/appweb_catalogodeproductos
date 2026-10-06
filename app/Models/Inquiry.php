<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Inquiry extends Model
{
    use HasSpanishAliases;

    protected $table = 'consultas';

    protected $fillable = [
        'client_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'message',
        'public_token',
        'source',
        'status',
        'total_estimated',
        'coupon_code',
        'coupon_consumed',
        'discount_amount',
        'exchange_rate',
        'handled_by',
        'contacted_at',
    ];

    protected $hidden = [
        'token_publico',
    ];

    protected function aliases(): array
    {
        return [
            'client_id' => 'cliente_id',
            'customer_name' => 'cliente_nombre',
            'customer_phone' => 'cliente_telefono',
            'customer_email' => 'cliente_email',
            'message' => 'mensaje',
            'source' => 'origen',
            'status' => 'estado',
            'exchange_rate' => 'tipo_cambio',
            'total_estimated' => 'total_estimado',
            'coupon_code' => 'cupon_codigo',
            'coupon_consumed' => 'cupon_consumido',
            'discount_amount' => 'descuento_monto',
            'handled_by' => 'atendido_por',
            'contacted_at' => 'contactado_en',
            'public_token' => 'token_publico',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Inquiry $inquiry): void {
            if (blank($inquiry->token_publico)) {
                $inquiry->token_publico = static::newPublicToken();
            }
        });

        // Invalidar el cache del dashboard cuando cambian los totales de consultas.
        static::saved(fn () => DashboardController::flushCache());
        static::deleted(fn () => DashboardController::flushCache());
    }

    protected function casts(): array
    {
        return [
            'total_estimado' => 'decimal:2',
            'tipo_cambio' => 'decimal:4',
            'cupon_consumido' => 'boolean',
            'descuento_monto' => 'decimal:2',
            'contactado_en' => 'datetime',
        ];
    }

    public const STATUSES = ['pendiente', 'contactado', 'vendido', 'cerrado'];

    public const SOURCES = ['whatsapp', 'web', 'telefono', 'otro'];

    public static function newPublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('token_publico', $token)->exists());

        return $token;
    }

    public function items(): HasMany
    {
        return $this->hasMany(InquiryItem::class, 'consulta_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cliente_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'consulta_id')->latest('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InquiryNote::class, 'consulta_id')->latest('id');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery(
            $query,
            $value,
            $field === 'public_token' ? 'token_publico' : $field,
        );
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Devuelve al cupón el uso que esta consulta tenía tomado.
     *
     * El uso se consume en el checkout, cuando todavía no se sabe si el pedido
     * va a terminar en venta. Si la consulta se cierra sin vender o se elimina,
     * ese uso vuelve: de lo contrario un cupón con tope se agota con pedidos
     * que nunca se concretaron.
     *
     * La bandera `cupon_consumido` es lo que hace idempotente la devolución:
     * cerrar dos veces, o cerrar y después eliminar, devuelve un solo uso.
     *
     * El código y el monto del descuento se conservan —son lo que se le prometió
     * al cliente y lo que usa la venta si la consulta se reabre y se vende—,
     * pero reabrirla no vuelve a tomar el uso: una vez devuelto, queda libre.
     */
    public function releaseCoupon(): bool
    {
        if (! $this->coupon_consumed) {
            return false;
        }

        $coupon = Coupon::where('codigo', $this->coupon_code)->lockForUpdate()->first();

        if ($coupon && $coupon->used_count > 0) {
            $coupon->decrement('usos_realizados');
        }

        $this->update(['coupon_consumed' => false]);

        return true;
    }
}
