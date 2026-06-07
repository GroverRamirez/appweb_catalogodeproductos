<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use HasSpanishAliases;

    protected $table = 'configuraciones';

    protected $fillable = [
        'key',
        'value',
        'group',
        'label',
        'type',
    ];

    protected const CACHE_KEY = 'app.settings.all';

    protected function aliases(): array
    {
        return [
            'key' => 'clave',
            'value' => 'valor',
            'group' => 'grupo',
            'label' => 'etiqueta',
            'type' => 'tipo',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Devuelve el valor (casteado) de una setting, o $default.
     * Se cachea un array plano [key => value] para evitar problemas de
     * deserialización de Eloquent Collections.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => self::loadFromDb());

        // Si el caché tiene un valor "raro" (Collection vieja, incomplete class, etc.),
        // lo regeneramos en caliente para no romper la app.
        if (! is_array($all)) {
            Cache::forget(self::CACHE_KEY);
            $all = self::loadFromDb();
            Cache::forever(self::CACHE_KEY, $all);
        }

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    protected static function loadFromDb(): array
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return [];
        }

        $map = [];
        foreach (static::query()->get(['clave', 'valor', 'tipo']) as $row) {
            $map[$row->clave] = self::castValue($row->valor, $row->tipo);
        }

        return $map;
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general', ?string $label = null): self
    {
        $stored = is_array($value) || is_object($value)
            ? json_encode($value)
            : (string) $value;

        $setting = static::query()->where('clave', $key)->first() ?? new static;

        $setting->fill([
            'key' => $key,
            'value' => $stored,
            'type' => $type,
            'group' => $group,
            'label' => $label,
        ]);
        $setting->save();

        return $setting;
    }

    protected static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : 0,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
