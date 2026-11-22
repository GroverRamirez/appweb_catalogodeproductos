<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Una línea del kardex: qué producto se movió, cuánto, por qué y con qué saldo
 * quedó. Se escribe siempre a través de InventoryLedger, nunca a mano, para
 * que no pueda existir un movimiento que no haya tocado el stock ni al revés.
 */
class InventoryMovement extends Model
{
    use HasSpanishAliases;

    protected $table = 'movimientos_inventario';

    public const TYPE_PURCHASE = 'compra';

    public const TYPE_SALE = 'venta';

    public const TYPE_PURCHASE_VOID = 'anulacion_compra';

    public const TYPE_SALE_VOID = 'anulacion_venta';

    public const TYPE_ADJUSTMENT = 'ajuste';

    public const TYPES = [
        self::TYPE_PURCHASE,
        self::TYPE_SALE,
        self::TYPE_PURCHASE_VOID,
        self::TYPE_SALE_VOID,
        self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'product_id',
        'product_name_snapshot',
        'product_code_snapshot',
        'type',
        'quantity',
        'previous_stock',
        'resulting_stock',
        'source_type',
        'source_id',
        'reason',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'stock_anterior' => 'integer',
            'stock_resultante' => 'integer',
        ];
    }

    protected function aliases(): array
    {
        return [
            'product_id' => 'producto_id',
            'product_name_snapshot' => 'producto_nombre_copia',
            'product_code_snapshot' => 'producto_codigo_copia',
            'type' => 'tipo',
            'quantity' => 'cantidad',
            'previous_stock' => 'stock_anterior',
            'resulting_stock' => 'stock_resultante',
            'source_type' => 'origen_type',
            'source_id' => 'origen_id',
            'reason' => 'motivo',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }

    /** Venta o compra que originó el movimiento; null si fue un ajuste. */
    public function source(): MorphTo
    {
        return $this->morphTo('origen');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('producto_id', $productId);
    }
}
