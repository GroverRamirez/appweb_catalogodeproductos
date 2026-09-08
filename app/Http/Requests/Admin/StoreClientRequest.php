<?php

namespace App\Http\Requests\Admin;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.create');
    }

    /**
     * El teléfono se normaliza ANTES de validar: si no, la regla unique
     * compararía "+591 70000000" contra el "70000000" ya guardado y dejaría
     * pasar el duplicado, que es justo lo que el índice único evita.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Client::normalizePhone($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('clientes', 'telefono')->whereNull('deleted_at'),
            ],
            'id_card' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'phone' => 'teléfono',
            'id_card' => 'carnet de identidad',
            'address' => 'dirección',
            'notes' => 'notas',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Ya existe un cliente con ese teléfono.',
        ];
    }
}
