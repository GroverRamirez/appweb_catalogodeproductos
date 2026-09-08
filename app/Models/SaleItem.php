<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasSpanishAliases;

    protected $table = 'venta_items';

    // Sin esto el accessor no se serializa y Vue no recibe el subtotal.
    protected $appends = ['subtotal'];

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name_snapshot',
        'product_code_snapshot',
        'quantity',
        'unit_price',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
        ];
    }

    protected function aliases(): array
    {
        return [
            'sale_id' => 'venta_id',
            'product_id' => 'producto_id',
            'product_name_snapshot' => 'producto_nombre_copia',
            'product_code_snapshot' => 'producto_codigo_copia',
            'quantity' => 'cantidad',
            'unit_price' => 'precio_unitario',
            'unit_cost' => 'costo_unitario',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'venta_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->unit_price * (int) $this->quantity;
    }
}
