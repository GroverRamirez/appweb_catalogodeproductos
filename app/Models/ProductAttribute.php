<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttribute extends Model
{
    use HasSpanishAliases;

    protected $table = 'producto_atributos';

    protected $fillable = [
        'product_id',
        'key',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    protected function aliases(): array
    {
        return [
            'product_id' => 'producto_id',
            'key' => 'clave',
            'value' => 'valor',
            'sort_order' => 'orden',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }
}
