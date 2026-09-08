<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Catálogo
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'banners.view', 'banners.create', 'banners.update', 'banners.delete',
            'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete',
            // Operación
            'inquiries.view', 'inquiries.update', 'inquiries.delete',
            'inventory.view', 'inventory.adjust',
            'sales.view', 'sales.create', 'sales.void',
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',
            // Administración
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'settings.view', 'settings.update',
            'reports.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Propietario: control total.
        $propietario = Role::firstOrCreate(['name' => 'propietario', 'guard_name' => 'web']);
        $propietario->syncPermissions(Permission::all());

        // Encargado: catálogo completo + operación, sin administración (usuarios/roles/configuración).
        $encargado = Role::firstOrCreate(['name' => 'encargado', 'guard_name' => 'web']);
        $encargado->syncPermissions([
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'banners.view', 'banners.create', 'banners.update', 'banners.delete',
            'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete',
            'inquiries.view', 'inquiries.update', 'inquiries.delete',
            'inventory.view', 'inventory.adjust',
            'sales.view', 'sales.create', 'sales.void',
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',
            'reports.view',
        ]);

        // Vendedor: operación limitada.
        $vendedor = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedor->syncPermissions([
            'categories.view',
            'brands.view',
            'products.view', 'products.update',
            'inquiries.view', 'inquiries.update',
            'inventory.view', 'inventory.adjust',
            // Vende, pero no anula: anular es la forma de hacer desaparecer
            // una operacion ya cobrada, asi que queda en encargado/propietario.
            'sales.view', 'sales.create',
            // Registra y corrige clientes al vender, pero no los da de baja.
            'clients.view', 'clients.create', 'clients.update',
            'reports.view',
        ]);

        // Cliente: solo navega el catálogo público.
        $cliente = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $cliente->syncPermissions([]);

        Artisan::call('cache:clear');
    }
}
