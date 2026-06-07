<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@catalogo.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
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
            $vendedor->assignRole('vendedor');
        }
    }
}
