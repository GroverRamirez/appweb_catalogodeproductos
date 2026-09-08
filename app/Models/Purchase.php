<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory, HasSpanishAliases;

    protected $table = 'compras';

    public const STATUSES = ['confirmada', 'anulada'];

    protected $fillable = [
        'supplier_id',
        'reference_number',
        'status',
        'total_cost',
        'notes',
        'created_by',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'costo_total' => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // El panel muestra gasto en compras y valor de inventario; ambos
        // cambian al registrar o anular una compra.
        static::saved(fn () => DashboardController::flushCache());
        static::deleted(fn () => DashboardController::flushCache());
    }

    protected function aliases(): array
    {
        return [
            'supplier_id' => 'proveedor_id',
            'reference_number' => 'numero_referencia',
            'status' => 'estado',
            'total_cost' => 'costo_total',
            'notes' => 'notas',
            'created_by' => 'creado_por',
            'voided_by' => 'anulado_por',
            'voided_at' => 'anulado_en',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'proveedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'compra_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
