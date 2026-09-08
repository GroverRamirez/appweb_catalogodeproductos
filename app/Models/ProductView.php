<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductView extends Model
{
    use HasSpanishAliases, Prunable;

    /** Meses de detalle de visitas que se conservan. */
    public const RETENTION_MONTHS = 6;

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

    /**
     * Esta tabla es un log de solo inserción: crece sin techo mientras haya
     * trafico. Se purga el detalle viejo, no el historico: el contador
     * acumulado vive en `productos.visitas` y lo incrementa RecordProductView
     * aparte, asi que purgar aca no pierde el total de vistas de un producto.
     */
    public function prunable(): Builder
    {
        return static::where('visto_en', '<=', now()->subMonths(self::RETENTION_MONTHS));
    }
}
