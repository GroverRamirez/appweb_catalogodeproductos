<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryItem extends Model
{
    use HasSpanishAliases;

    protected $table = 'consulta_items';

    protected $fillable = [
        'inquiry_id',
        'product_id',
        'product_name_snapshot',
        'product_code_snapshot',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
        ];
    }

    protected function aliases(): array
    {
        return [
            'inquiry_id' => 'consulta_id',
            'product_id' => 'producto_id',
            'product_name_snapshot' => 'producto_nombre_copia',
            'product_code_snapshot' => 'producto_codigo_copia',
            'quantity' => 'cantidad',
            'unit_price' => 'precio_unitario',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'consulta_id');
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
