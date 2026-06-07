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
            // Administración
            'users.view', 'users.create', 'users.update', 'users.delete',
            'settings.view', 'settings.update',
            'reports.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        $vendedor = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedor->syncPermissions([
            'categories.view',
            'brands.view',
            'products.view', 'products.update',
            'inquiries.view', 'inquiries.update',
            'inventory.view', 'inventory.adjust',
            'reports.view',
        ]);

        $cliente = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $cliente->syncPermissions([]); // Cliente solo navega el catálogo público

        Artisan::call('cache:clear');
    }
}
