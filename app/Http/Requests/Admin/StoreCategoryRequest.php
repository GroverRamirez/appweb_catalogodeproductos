<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('categories.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('categorias', 'slug')],
            'description' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|exists:categorias,id',
            'image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
