<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => trim((string) $this->input('customer_phone')),
            'customer_email' => $this->filled('customer_email') ? trim((string) $this->input('customer_email')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : null,
            'source' => $this->filled('source') ? strtolower(trim((string) $this->input('source'))) : 'web',
            'coupon_code' => $this->filled('coupon_code') ? strtoupper(trim((string) $this->input('coupon_code'))) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s().-]{6,30}$/'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', Rule::in(['whatsapp', 'web', 'telefono', 'otro'])],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required',
                Rule::exists(Product::class, 'id')->where(fn ($query) => $query
                    ->where('activo', true)
                    ->whereNull('deleted_at')),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
