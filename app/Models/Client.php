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
     * Normaliza el carnet: sin espacios sobrantes y en mayúsculas.
     *
     * El complemento y la extensión ("1234567 LP", "1234567-1B") se escriben
     * de mil formas en el mostrador; sin esto la búsqueda por CI no encuentra
     * a quien ya está registrado y se duplica la ficha.
     */
    public static function normalizeIdCard(?string $value): ?string
    {
        $clean = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)));

        return $clean === '' ? null : $clean;
    }

    public function setCarnetIdentidadAttribute(?string $value): void
    {
        $this->attributes['carnet_identidad'] = static::normalizeIdCard($value);
    }

    /**
     * Cliente vigente con ese carnet, o null. Es lo que consulta el formulario
     * de venta para autocompletar los datos apenas se escribe el CI.
     */
    public static function findByIdCard(?string $idCard): ?self
    {
        $normalized = static::normalizeIdCard($idCard);

        if ($normalized === null) {
            return null;
        }

        // El carnet no es único en la tabla (hay fichas viejas sin depurar):
        // ante dos con el mismo, gana la más reciente.
        return static::query()->where('carnet_identidad', $normalized)->latest('id')->first();
    }

    /**
     * Resuelve la ficha del cliente de una venta de mostrador.
     *
     * Con CI, el carnet es la identidad: si ya existe se reusa y se actualiza
     * con lo que se escribió en el mostrador (nombre y teléfono), y si no, se
     * busca por teléfono antes de crear una ficha nueva. Sin CI se sigue el
     * camino de siempre por teléfono, sin tocar la ficha encontrada.
     */
    public static function resolveForSale(?string $idCard, ?string $phone, ?string $name): ?self
    {
        $idCard = static::normalizeIdCard($idCard);
        $name = trim((string) $name);

        if ($idCard === null) {
            return static::resolveByPhone($phone, ['name' => $name ?: 'Cliente sin nombre']);
        }

        $phone = static::normalizePhone($phone);

        $client = static::withTrashed()->where('carnet_identidad', $idCard)->latest('id')->first();

        if (! $client && $phone !== null) {
            $byPhone = static::withTrashed()->where('telefono', $phone)->first();

            // Ese teléfono es de alguien con otro carnet: no es la misma
            // persona, así que no se le pisa la ficha.
            if ($byPhone && ($byPhone->id_card === null || $byPhone->id_card === $idCard)) {
                $client = $byPhone;
            }
        }

        if (! $client) {
            return static::create([
                'name' => $name ?: 'Cliente sin nombre',
                'id_card' => $idCard,
                // El teléfono es único: si ya lo usa otra ficha, la nueva
                // queda sin él (la venta igual lo guarda en su copia).
                'phone' => $phone !== null && static::withTrashed()->where('telefono', $phone)->doesntExist()
                    ? $phone
                    : null,
            ]);
        }

        if ($client->trashed()) {
            $client->restore();
        }

        if ($name !== '') {
            $client->name = $name;
        }

        if ($client->id_card === null) {
            $client->id_card = $idCard;
        }

        if ($phone !== null && $phone !== $client->phone
            && static::withTrashed()->where('telefono', $phone)->whereKeyNot($client->id)->doesntExist()) {
            $client->phone = $phone;
        }

        $client->save();

        return $client;
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
