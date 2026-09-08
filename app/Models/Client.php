<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, HasSpanishAliases, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'id_card',
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
            'phone' => 'telefono',
            'id_card' => 'carnet_identidad',
            'address' => 'direccion',
            'notes' => 'notas',
            'is_active' => 'activo',
        ];
    }

    /**
     * Normaliza el teléfono a solo dígitos y sin el código de país.
     *
     * Sin esto el índice único no sirve: "70000000", "+591 70000000" y
     * "591-70000000" son la misma persona pero tres valores distintos, y se
     * duplicarían los clientes igual.
     */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        // Los celulares bolivianos son de 8 dígitos; si viene con el 591
        // delante, se descarta para que coincida con el que se cargó a mano.
        if (strlen($digits) > 8 && str_starts_with($digits, '591')) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }

    public function setTelefonoAttribute(?string $value): void
    {
        $this->attributes['telefono'] = static::normalizePhone($value);
    }

    /**
     * Busca al cliente por teléfono, o lo crea. Es el camino que usan tanto la
     * venta de mostrador como el checkout público, para que el mismo cliente no
     * termine duplicado según por dónde entró.
     *
     * Sin teléfono no se puede identificar a nadie, así que devuelve null en
     * vez de crear un cliente anónimo por cada venta al paso.
     *
     * @param  array<string, mixed>  $attributes  datos con los que crearlo si no existe
     */
    public static function resolveByPhone(?string $phone, array $attributes = []): ?self
    {
        $normalized = static::normalizePhone($phone);

        if ($normalized === null) {
            return null;
        }

        $client = static::withTrashed()->where('telefono', $normalized)->first();

        if ($client) {
            // Un cliente que vuelve después de haber sido dado de baja se
            // reactiva: existe y está comprando.
            if ($client->trashed()) {
                $client->restore();
            }

            return $client;
        }

        return static::create([...$attributes, 'phone' => $normalized]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'cliente_id')->latest('id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'cliente_id')->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
