<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidImageMime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.create');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('productos', 'codigo')],
            'name' => 'required|string|max:200',
            'slug' => ['nullable', 'string', 'max:220', Rule::unique('productos', 'slug')],
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:10000',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0|lte:price',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:20',
            'category_id' => 'nullable|exists:categorias,id',
            'brand_id' => 'nullable|exists:marcas,id',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'attributes' => 'array',
            'attributes.*.key' => 'nullable|string|max:100',
            'attributes.*.value' => 'nullable|string|max:255',
            'images' => 'nullable|array|max:8',
            'images.*' => ['image', 'max:4096', new ValidImageMime],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
