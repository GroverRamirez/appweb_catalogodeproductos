<?php

namespace App\Models\Concerns;

trait HasSpanishAliases
{
    /**
     * @return array<string, string>
     */
    abstract protected function aliases(): array;

    public function getAttribute($key): mixed
    {
        if (is_string($key) && array_key_exists($key, $this->aliases())) {
            return parent::getAttribute($this->aliases()[$key]);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value): mixed
    {
        if (is_string($key) && array_key_exists($key, $this->aliases())) {
            return parent::setAttribute($this->aliases()[$key], $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();
        $hidden = parent::getHidden();

        foreach ($this->aliases() as $alias => $column) {
            if (array_key_exists($column, $attributes)) {
                if (! in_array($alias, $hidden, true) && ! in_array($column, $hidden, true)) {
                    $attributes[$alias] = $attributes[$column];
                }

                unset($attributes[$column]);
            }
        }

        return $attributes;
    }
}
