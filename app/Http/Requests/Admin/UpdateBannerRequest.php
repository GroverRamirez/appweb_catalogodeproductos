<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidImageMime;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('banners.update');
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
