<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductCostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust');
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:productos,id',
            // Vacío = "todavía no sé el costo": esa fila se ignora al guardar,
            // nunca borra un costo ya cargado.
            'items.*.cost' => 'nullable|numeric|min:0|max:99999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.cost.numeric' => 'El costo debe ser un número.',
            'items.*.cost.min' => 'El costo no puede ser negativo.',
        ];
    }
}
