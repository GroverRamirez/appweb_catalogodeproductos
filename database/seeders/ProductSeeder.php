<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $leafCategoryIds = Category::query()->whereNotNull('categoria_padre_id')->pluck('id');

        if ($leafCategoryIds->isEmpty()) {
            $this->command?->warn('No hay categorias hijo. Saltando ProductSeeder.');

            return;
        }

        $demoProducts = Product::query()
            ->where('codigo', 'like', 'P-%')
            ->orderBy('id')
            ->get();
        $targetCount = max(60, $demoProducts->count());
        $createdOrUpdated = 0;

        for ($i = 0; $i < $targetCount; $i++) {
            $product = $demoProducts->get($i);
            $data = Product::factory()->make([
                'categoria_id' => $leafCategoryIds->random(),
            ])->getAttributes();

            if ($product) {
                $data['slug'] = Product::uniqueSlug($data['nombre'], $product->id);
                $product->forceFill($data)->save();
            } else {
                $product = Product::factory()->create($data);
            }

            $this->replaceProductImages($product);

            ProductAttribute::query()->where('producto_id', $product->id)->delete();
            ProductAttribute::insert([
                [
                    'producto_id' => $product->id,
                    'clave' => 'Garantia',
                    'valor' => '12 meses',
                    'orden' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'producto_id' => $product->id,
                    'clave' => 'Origen',
                    'valor' => fake()->randomElement(['Importado', 'Nacional']),
                    'orden' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $createdOrUpdated++;
        }

        Product::query()->update(['destacado' => false]);
        Product::query()->inRandomOrder()->limit(8)->update(['destacado' => true]);

        $this->command?->info("ProductSeeder: $createdOrUpdated productos demo creados o actualizados, 8 marcados como destacados.");
    }

    protected function replaceProductImages(Product $product): void
    {
        foreach ($product->images as $image) {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                Storage::disk('public')->delete($image->path);
            }
        }

        ProductImage::query()->where('producto_id', $product->id)->delete();

        foreach ($this->productPhotoUrls($product) as $index => $url) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $url,
                'alt' => $product->name,
                'sort_order' => $index,
                'is_main' => $index === 0,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function productPhotoUrls(Product $product): array
    {
        return $this->photoSetFor($product->name);
    }

    /**
     * @return array<int, string>
     */
    protected function photoSetFor(string $name): array
    {
        $normalizedName = Str::lower($name);

        $photoSets = [
            'audifonos' => [
                'photo-1505740420928-5e560c06d30e',
                'photo-1484704849700-f032a568e944',
                'photo-1545127398-14699f92334b',
            ],
            'laptop' => [
                'photo-1517336714731-489689fd1ca8',
                'photo-1496181133206-80ce9b88a853',
                'photo-1516321318423-f06f85e504b3',
            ],
            'mochila' => [
                'photo-1553062407-98eeb64c6a62',
                'photo-1622560480605-d83c853bc5c3',
                'photo-1547949003-9792a18a2601',
            ],
            'silla' => [
                'photo-1567538096630-e0c55bd6374c',
                'photo-1585036156171-384164a8c675',
                'photo-1519947486511-46149fa0a254',
            ],
            'escritorio' => [
                'photo-1518455027359-f3f8164ba6bd',
                'photo-1519710164239-da123dc03ef4',
                'photo-1524758631624-e2822e304c36',
            ],
            'lampara' => [
                'photo-1507473885765-e6ed057f782c',
                'photo-1513506003901-1e6a229e2d15',
                'photo-1494438639946-1ebd1d20bf85',
            ],
            'cocina' => [
                'photo-1556911220-bff31c812dba',
                'photo-1583241475880-6832f9c6c95f',
                'photo-1587302164675-820fe61bbd55',
            ],
            'organizador' => [
                'photo-1516321497487-e288fb19713f',
                'photo-1520607162513-77705c0f0d4a',
                'photo-1497366754035-f200968a6e72',
            ],
            'zapatillas' => [
                'photo-1542291026-7eec264c27ff',
                'photo-1549298916-b41d501d3772',
                'photo-1608231387042-66d1773070a5',
            ],
            'bicicleta' => [
                'photo-1485965120184-e220f721d03e',
                'photo-1507035895480-2b3156c31fc8',
                'photo-1532298229144-0ec0c57515c7',
            ],
            'reloj' => [
                'photo-1523275335684-37898b6baf30',
                'photo-1434056886845-dac89ffe9b56',
                'photo-1524592094714-0f0654e20314',
            ],
            'parlante' => [
                'photo-1545454675-3531b543be5d',
                'photo-1608043152269-423dbba4e7e1',
                'photo-1542193810-9007c21cd37e',
            ],
            'mouse' => [
                'photo-1527864550417-7fd91fc51a46',
                'photo-1615663245857-ac93bb7c39e7',
                'photo-1613141411244-0e4ac259d217',
            ],
            'teclado' => [
                'photo-1587829741301-dc798b83add3',
                'photo-1595225476474-87563907a212',
                'photo-1618384887929-16ec33fab9ef',
            ],
            'monitor' => [
                'photo-1527443224154-c4a3942d3acf',
                'photo-1498050108023-c5249f4df085',
                'photo-1547082299-de196ea013d6',
            ],
            'cargador' => [
                'photo-1609091839311-d5365f9ff1c5',
                'photo-1581090464777-f3220bbe1b8b',
                'photo-1615526675159-e248c3021d3f',
            ],
            'botella' => [
                'photo-1602143407151-7111542de6e8',
                'photo-1523362628745-0c100150b504',
                'photo-1599297916367-0c84f39b3e8f',
            ],
            'cuidado facial' => [
                'photo-1571781926291-c477ebfd024b',
                'photo-1556228578-8c89e6adf883',
                'photo-1598440947619-2c35fc9aa908',
            ],
            'secadora' => [
                'photo-1522338242992-e1a54906a8da',
                'photo-1522337360788-8b13dee7a37e',
                'photo-1562322140-8baeececf3df',
            ],
            'perfume' => [
                'photo-1541643600914-78b084683601',
                'photo-1594035910387-fea47794261f',
                'photo-1587017539504-67cfbddac569',
            ],
        ];

        foreach ($photoSets as $keyword => $photos) {
            if (str_contains($normalizedName, $keyword)) {
                return $this->photoUrls($photos);
            }
        }

        $fallbackPhotos = [
            'photo-1523275335684-37898b6baf30',
            'photo-1542291026-7eec264c27ff',
            'photo-1505740420928-5e560c06d30e',
            'photo-1517336714731-489689fd1ca8',
            'photo-1602143407151-7111542de6e8',
        ];

        $offset = abs(crc32($normalizedName)) % 3;

        return $this->photoUrls(array_slice($fallbackPhotos, $offset, 3));
    }

    /**
     * @param  array<int, string>  $photoIds
     * @return array<int, string>
     */
    protected function photoUrls(array $photoIds): array
    {
        return array_map(fn (string $photoId): string => $this->photoUrl($photoId), $photoIds);
    }

    protected function photoUrl(string $photoId): string
    {
        return "https://images.unsplash.com/{$photoId}?auto=format&fit=crop&w=900&h=900&q=80";
    }
}
