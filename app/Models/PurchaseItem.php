<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasSpanishAliases;

    protected $table = 'compra_items';

    protected $appends = ['subtotal'];

    protected $fillable = [
        'purchase_id',
        'product_id',
        'product_name_snapshot',
        'product_code_snapshot',
        'quantity',
        'unit_cost',
        'previous_cost',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'costo_unitario' => 'decimal:2',
            'costo_anterior' => 'decimal:2',
        ];
    }

    protected function aliases(): array
    {
        return [
            'purchase_id' => 'compra_id',
            'product_id' => 'producto_id',
            'product_name_snapshot' => 'producto_nombre_copia',
            'product_code_snapshot' => 'producto_codigo_copia',
            'quantity' => 'cantidad',
            'unit_cost' => 'costo_unitario',
            'previous_cost' => 'costo_anterior',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'compra_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->unit_cost * (int) $this->quantity;
    }
}
