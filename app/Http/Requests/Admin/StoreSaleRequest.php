<?php

namespace App\Http\Requests\Admin;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.create');
    }

    public function rules(): array
    {
        return [
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'id_card' => 'nullable|string|max:30',
            'payment_method' => ['required', Rule::in(Sale::PAYMENT_METHODS)],
            // El tope (descuento <= subtotal) se valida en el controlador,
            // donde el subtotal ya esta calculado desde la base: aca todavia
            // no hay contra que compararlo sin confiar en montos del cliente.
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:productos,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => 'nombre del cliente',
            'customer_phone' => 'teléfono',
            'id_card' => 'carnet de identidad',
            'payment_method' => 'método de pago',
            'discount_amount' => 'descuento',
            'items' => 'productos',
        ];
    }
}
