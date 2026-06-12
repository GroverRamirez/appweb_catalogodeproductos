<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Renombra el rol `admin` (esquema antiguo) a `propietario` conservando
     * sus asignaciones de usuarios y permisos. Idempotente: no falla si el rol
     * `admin` no existe o si `propietario` ya está presente.
     */
    public function up(): void
    {
        $hasAdmin = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->exists();
        $hasPropietario = DB::table('roles')->where('name', 'propietario')->where('guard_name', 'web')->exists();

        if ($hasAdmin && ! $hasPropietario) {
            DB::table('roles')
                ->where('name', 'admin')
                ->where('guard_name', 'web')
                ->update(['name' => 'propietario']);
        }
    }

    /**
     * Revierte el renombrado de `propietario` a `admin`.
     */
    public function down(): void
    {
        $hasPropietario = DB::table('roles')->where('name', 'propietario')->where('guard_name', 'web')->exists();
        $hasAdmin = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->exists();

        if ($hasPropietario && ! $hasAdmin) {
            DB::table('roles')
                ->where('name', 'propietario')
                ->where('guard_name', 'web')
                ->update(['name' => 'admin']);
        }
    }
};
