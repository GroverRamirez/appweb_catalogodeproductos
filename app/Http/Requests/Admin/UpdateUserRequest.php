<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
    }

    public function rules(): array
    {
        $id = $this->route('user')->id;

        return [
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'string', Password::default()],
            'role' => 'nullable|string|exists:roles,name',
        ];
    }
}
