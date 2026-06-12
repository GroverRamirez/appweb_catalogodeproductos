<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('roles.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:60', 'regex:/^[\pL\pN _-]+$/u',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Role $role */
                $role = $this->route('role');

                if (! in_array($role->name, AdminGuard::PROTECTED_ROLES, true)) {
                    return;
                }

                if ($this->input('name') !== $role->name) {
                    $validator->errors()->add('name', 'Este rol del sistema no puede renombrarse.');
                }

                $requestedPermissions = collect($this->input('permissions', []))
                    ->filter()
                    ->sort()
                    ->values();

                $currentPermissions = $role->permissions()
                    ->pluck('name')
                    ->sort()
                    ->values();

                if ($requestedPermissions->diff($currentPermissions)->isNotEmpty()
                    || $currentPermissions->diff($requestedPermissions)->isNotEmpty()
                ) {
                    $validator->errors()->add('permissions', 'Los permisos de este rol del sistema no pueden modificarse.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras, números, espacios, guiones y guiones bajos.',
        ];
    }
}
