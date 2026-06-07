<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidImageMime;
use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('banners.create');
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:150',
            'subtitle' => 'nullable|string|max:255',
            'image_file' => ['nullable', 'image', 'max:4096', new ValidImageMime],
            'image_url' => 'nullable|url|max:500',
            'link' => 'nullable|string|max:500',
            'cta_text' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->hasFile('image_file') && ! $this->filled('image_url')) {
                $v->errors()->add('image_file', 'Sube una imagen o indica una URL.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
