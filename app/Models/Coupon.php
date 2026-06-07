<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasSpanishAliases;

    protected $table = 'cupones';

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_subtotal',
        'max_uses',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'subtotal_minimo' => 'decimal:2',
            'usos_maximos' => 'integer',
            'usos_realizados' => 'integer',
            'activo' => 'boolean',
            'inicia_en' => 'datetime',
            'termina_en' => 'datetime',
        ];
    }

    protected function aliases(): array
    {
        return [
            'code' => 'codigo',
            'description' => 'descripcion',
            'type' => 'tipo',
            'value' => 'valor',
            'min_subtotal' => 'subtotal_minimo',
            'max_uses' => 'usos_maximos',
            'used_count' => 'usos_realizados',
            'starts_at' => 'inicia_en',
            'ends_at' => 'termina_en',
            'is_active' => 'activo',
        ];
    }

    public const TYPES = ['percent', 'fixed'];

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('activo', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('inicia_en')->orWhere('inicia_en', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('termina_en')->orWhere('termina_en', '>=', $now);
            });
    }

    public function isUsable(?float $subtotal = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isBefore($now)) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->min_subtotal !== null && $subtotal !== null && $subtotal < (float) $this->min_subtotal) {
            return false;
        }

        return true;
    }

    /**
     * Calcula el descuento aplicado al subtotal.
     */
    public function discountFor(float $subtotal): float
    {
        if (! $this->isUsable($subtotal)) {
            return 0.0;
        }

        if ($this->type === 'percent') {
            return round($subtotal * ((float) $this->value / 100), 2);
        }

        return min((float) $this->value, $subtotal);
    }
}
