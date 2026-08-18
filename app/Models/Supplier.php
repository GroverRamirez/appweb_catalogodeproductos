<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, HasSpanishAliases, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected function aliases(): array
    {
        return [
            'name' => 'nombre',
            'contact_name' => 'contacto_nombre',
            'phone' => 'telefono',
            'address' => 'direccion',
            'notes' => 'notas',
            'is_active' => 'activo',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'proveedor_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
