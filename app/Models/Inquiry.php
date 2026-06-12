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
        'customer_name',
        'customer_phone',
        'customer_email',
        'message',
        'public_token',
        'source',
        'status',
        'total_estimated',
        'coupon_code',
        'discount_amount',
        'admin_notes',
        'handled_by',
        'contacted_at',
    ];

    protected $hidden = [
        'token_publico',
    ];

    protected function aliases(): array
    {
        return [
            'customer_name' => 'cliente_nombre',
            'customer_phone' => 'cliente_telefono',
            'customer_email' => 'cliente_email',
            'message' => 'mensaje',
            'source' => 'origen',
            'status' => 'estado',
            'total_estimated' => 'total_estimado',
            'coupon_code' => 'cupon_codigo',
            'discount_amount' => 'descuento_monto',
            'admin_notes' => 'notas_admin',
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
}
