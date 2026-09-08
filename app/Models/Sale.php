<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, HasSpanishAliases;

    protected $table = 'ventas';

    public const STATUSES = ['confirmada', 'anulada'];

    public const PAYMENT_METHODS = ['efectivo', 'qr', 'transferencia', 'tarjeta'];

    public const ORIGINS = ['mostrador', 'consulta'];

    protected $fillable = [
        'receipt_number',
        'inquiry_id',
        'client_id',
        'origin',
        'customer_name',
        'customer_phone',
        'payment_method',
        'subtotal',
        'discount_amount',
        'total',
        'status',
        'notes',
        'created_by',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'numero_recibo' => 'integer',
            'subtotal' => 'decimal:2',
            'descuento_monto' => 'decimal:2',
            'total' => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // El panel muestra ingresos y valor de inventario; ambos cambian al
        // registrar o anular una venta.
        static::saved(fn () => DashboardController::flushCache());
        static::deleted(fn () => DashboardController::flushCache());
    }

    protected function aliases(): array
    {
        return [
            'receipt_number' => 'numero_recibo',
            'inquiry_id' => 'consulta_id',
            'client_id' => 'cliente_id',
            'origin' => 'origen',
            'customer_name' => 'cliente_nombre',
            'customer_phone' => 'cliente_telefono',
            'payment_method' => 'metodo_pago',
            'discount_amount' => 'descuento_monto',
            'status' => 'estado',
            'notes' => 'notas',
            'created_by' => 'creado_por',
            'voided_by' => 'anulado_por',
            'voided_at' => 'anulado_en',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'consulta_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cliente_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'venta_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * Reserva el siguiente número de recibo. Se llama SIEMPRE dentro de la
     * transacción que crea la venta: el `lockForUpdate` bloquea a otra
     * transacción concurrente hasta el commit, y el índice único de la columna
     * cubre el caso de que ese bloqueo no alcance.
     */
    public static function nextReceiptNumber(): int
    {
        return (int) static::query()->lockForUpdate()->max('numero_recibo') + 1;
    }
}
