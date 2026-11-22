<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Crea el permiso `backups.manage` y se lo da al propietario, para que las
     * bases ya sembradas lo tengan sin volver a correr el RoleSeeder.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'backups.manage', 'guard_name' => 'web']);

        Role::where('name', 'propietario')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'backups.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
