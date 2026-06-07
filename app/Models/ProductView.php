<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductView extends Model
{
    use HasSpanishAliases;

    public $timestamps = false;

    protected $table = 'producto_visitas';

    protected $fillable = [
        'product_id',
        'ip_address',
        'session_id',
        'user_agent',
        'referrer',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'visto_en' => 'datetime',
        ];
    }

    protected function aliases(): array
    {
        return [
            'product_id' => 'producto_id',
            'ip_address' => 'direccion_ip',
            'session_id' => 'sesion_id',
            'user_agent' => 'agente_usuario',
            'referrer' => 'referente',
            'viewed_at' => 'visto_en',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }
}
