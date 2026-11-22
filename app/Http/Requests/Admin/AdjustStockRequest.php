<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust');
    }

    public function rules(): array
    {
        return [
            // Se pide el stock contado, no la diferencia: quien ajusta viene de
            // contar la estantería y piensa en "quedan 12", no en "sumá 3".
            'new_stock' => ['required', 'integer', 'min:0', 'max:9999999'],
            // Obligatorio a propósito: un ajuste sin motivo es exactamente el
            // agujero que el kardex vino a cerrar.
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'new_stock' => 'stock contado',
            'reason' => 'motivo',
        ];
    }
}
