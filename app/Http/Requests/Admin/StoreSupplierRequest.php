<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'contact_name' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ];
    }
}
