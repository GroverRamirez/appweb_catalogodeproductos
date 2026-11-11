<?php

namespace App\Http\Requests\Catalog;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class CompleteClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Se normaliza ANTES de validar, igual que en la ficha del panel: así
     * "+591 7000-0000" se valida como los 8 dígitos que se van a guardar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_card' => Client::normalizeIdCard($this->input('id_card')),
            'phone' => Client::normalizePhone($this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'id_card' => ['required', 'string', 'max:30', 'regex:/^\d{4,}[0-9A-Z .\-]*$/'],
            'phone' => ['required', 'string', 'regex:/^\d{7,15}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_card' => 'carnet de identidad',
            'phone' => 'teléfono',
        ];
    }

    public function messages(): array
    {
        return [
            'id_card.regex' => 'Escribe tu CI con sus números y, si tiene, la extensión (por ejemplo, 1234567 LP).',
            'phone.regex' => 'Escribe un teléfono válido, por ejemplo 70000000.',
        ];
    }
}
