<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $propietario = User::updateOrCreate(
            ['email' => 'admin@catalogo.test'],
            [
                'name' => 'Propietario',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $propietario->hasRole('propietario')) {
            $propietario->syncRoles(['propietario']);
        }

        $encargado = User::updateOrCreate(
            ['email' => 'encargado@catalogo.test'],
            [
                'name' => 'Encargado Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $encargado->hasRole('encargado')) {
            $encargado->syncRoles(['encargado']);
        }

        $vendedor = User::updateOrCreate(
            ['email' => 'vendedor@catalogo.test'],
            [
                'name' => 'Vendedor Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $vendedor->hasRole('vendedor')) {
            $vendedor->syncRoles(['vendedor']);
        }
    }
}
