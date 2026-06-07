<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Samsung', 'Apple', 'Xiaomi', 'Sony', 'LG', 'HP', 'Lenovo',
            'Dell', 'Asus', 'Logitech', 'Nike', 'Adidas', 'Bosch',
            'Philips', 'Generico',
        ];

        foreach ($brands as $name) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'nombre' => $name,
                    'descripcion' => 'Productos de la marca '.$name,
                    'activo' => true,
                ]
            );
        }
    }
}
