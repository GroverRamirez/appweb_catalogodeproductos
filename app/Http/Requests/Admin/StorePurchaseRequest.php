<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'nullable|integer|exists:proveedores,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:productos,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ];
    }
}
