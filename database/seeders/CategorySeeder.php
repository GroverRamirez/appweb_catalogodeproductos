<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Tecnologia' => ['Laptops', 'Smartphones', 'Accesorios', 'Audio'],
            'Hogar' => ['Cocina', 'Muebles', 'Decoracion'],
            'Deportes' => ['Fitness', 'Ciclismo', 'Indumentaria'],
            'Oficina' => ['Papeleria', 'Mobiliario', 'Tecnologia de oficina'],
            'Belleza y cuidado' => ['Cuidado facial', 'Cabello', 'Fragancias'],
        ];

        $order = 0;
        foreach ($tree as $rootName => $children) {
            $root = Category::updateOrCreate(
                ['slug' => Str::slug($rootName)],
                [
                    'nombre' => $rootName,
                    'descripcion' => 'Categoria '.$rootName,
                    'categoria_padre_id' => null,
                    'orden' => $order++,
                    'activo' => true,
                ]
            );

            $childOrder = 0;
            foreach ($children as $childName) {
                Category::updateOrCreate(
                    ['slug' => Str::slug($rootName.'-'.$childName)],
                    [
                        'nombre' => $childName,
                        'descripcion' => $childName.' dentro de '.$rootName,
                        'categoria_padre_id' => $root->id,
                        'orden' => $childOrder++,
                        'activo' => true,
                    ]
                );
            }
        }
    }
}
