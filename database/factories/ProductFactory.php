<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $productTypes = [
            'Audifonos Bluetooth',
            'Laptop ultraligera',
            'Mochila ejecutiva',
            'Silla ergonomica',
            'Escritorio compacto',
            'Lampara LED',
            'Set de cocina',
            'Organizador modular',
            'Zapatillas deportivas',
            'Bicicleta urbana',
            'Reloj inteligente',
            'Parlante portatil',
            'Mouse inalambrico',
            'Teclado mecanico',
            'Monitor panoramico',
            'Cargador rapido',
            'Botella termica',
            'Kit de cuidado facial',
            'Secadora profesional',
            'Perfume clasico',
        ];
        $features = [
            'para uso diario',
            'con garantia extendida',
            'de alta resistencia',
            'edicion premium',
            'modelo compacto',
            'para oficina',
            'con acabado moderno',
            'de bajo consumo',
            'para entrenamiento',
            'con diseno renovado',
        ];
        $name = fake()->randomElement($productTypes).' '.fake()->randomElement($features).' '.fake()->numberBetween(100, 999);
        $price = fake()->randomFloat(2, 5, 2000);
        $onSale = fake()->boolean(20);

        return [
            'codigo' => 'P-'.strtoupper(Str::random(8)),
            'nombre' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'descripcion_corta' => fake()->randomElement([
                'Producto seleccionado para uso diario con buena relacion precio calidad.',
                'Ideal para clientes que buscan rendimiento, durabilidad y diseno practico.',
                'Articulo recomendado para el hogar, oficina o actividades cotidianas.',
                'Opcion versatil con stock disponible y entrega coordinada en zona local.',
            ]),
            'descripcion' => fake()->randomElement([
                'Producto de prueba para el catalogo, pensado para mostrar informacion comercial clara, precio referencial, stock y atributos principales. Incluye datos suficientes para validar busquedas, filtros, detalle del producto y consultas por WhatsApp.',
                'Articulo demo con descripcion en espanol para revisar la experiencia del catalogo publico y del panel administrativo. Puede usarse para probar categorias, marcas, descuentos, stock bajo e imagenes de producto.',
                'Registro de ejemplo creado para validar la gestion de productos. La informacion es referencial y permite probar flujos de administracion, consultas de clientes, reportes y visualizacion de fichas comerciales.',
            ]),
            'precio' => $price,
            'precio_oferta' => $onSale ? round($price * fake()->randomFloat(2, 0.5, 0.9), 2) : null,
            'costo' => round($price * 0.6, 2),
            'stock' => fake()->numberBetween(0, 80),
            'stock_minimo' => 5,
            'unidad' => fake()->randomElement(['unidad', 'caja', 'kg', 'litro', 'paquete']),
            'categoria_id' => Category::query()->inRandomOrder()->value('id') ?? Category::factory(),
            'marca_id' => Brand::query()->inRandomOrder()->value('id') ?? Brand::factory(),
            'destacado' => fake()->boolean(15),
            'activo' => true,
            'visitas' => fake()->numberBetween(0, 500),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['destacado' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }
}
