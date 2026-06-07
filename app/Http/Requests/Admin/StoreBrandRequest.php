<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('brands.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('marcas', 'slug')],
            'description' => 'nullable|string|max:2000',
            'logo' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ];
    }
}
