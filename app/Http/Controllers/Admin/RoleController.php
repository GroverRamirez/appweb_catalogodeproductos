<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Support\AdminGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($request->string('q')->toString(), function ($query, $term) {
                $query->where('name', 'like', "%$term%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/roles/Index', [
            'roles' => $roles->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'is_protected' => in_array($role->name, AdminGuard::PROTECTED_ROLES, true),
            ]),
            'filters' => $request->only(['q']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/Form', [
            'role' => null,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return to_route('admin.roles.index')->with('success', 'Rol creado.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('admin/roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'is_protected' => in_array($role->name, AdminGuard::PROTECTED_ROLES, true),
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        if (! in_array($role->name, AdminGuard::PROTECTED_ROLES, true)) {
            $role->name = $data['name'];
            $role->save();
        }

        $role->syncPermissions($data['permissions'] ?? []);

        return to_route('admin.roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, AdminGuard::PROTECTED_ROLES, true)) {
            return back()->with('error', 'Este rol del sistema no puede eliminarse.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'No puedes eliminar un rol con usuarios asignados.');
        }

        $role->delete();

        return to_route('admin.roles.index')->with('success', 'Rol eliminado.');
    }

    /**
     * Permisos agrupados por módulo (prefijo antes del punto) para el formulario.
     *
     * @return Collection<string, Collection<int, string>>
     */
    private function permissionGroups(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0]);
    }
}
