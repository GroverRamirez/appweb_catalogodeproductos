<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Limpiar cache entre tests para evitar que cachedAll/cachedHomeRoots
    // devuelvan datos de un test anterior.
    Cache::flush();
});

// ─── HOME ─────────────────────────────────────────────────────────────────────

test('home page renders with correct component', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Home')
            ->has('banners')
            ->has('featured')
            ->has('newest')
            ->has('categories')
            ->has('seo')
        );
});

test('home page seo has canonical pointing to root', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', url('/'))
        );
});

test('home page returns featured products', function () {
    Product::factory()->count(3)->create(['destacado' => true, 'activo' => true]);
    Product::factory()->count(2)->create(['destacado' => false, 'activo' => true]);

    Cache::flush();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('featured', 3)
        );
});

test('home page does not return inactive products as featured', function () {
    Product::factory()->count(2)->create(['destacado' => true, 'activo' => false]);

    Cache::flush();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('featured', 0)
        );
});

// ─── CATALOG INDEX ────────────────────────────────────────────────────────────

test('catalog index renders with correct component and props', function () {
    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Index')
            ->has('products')
            ->has('products.data')
            ->has('filters')
            ->has('categories')
            ->has('brands')
            ->has('seo')
        );
});

test('catalog index shows only active products', function () {
    Product::factory()->count(3)->create(['activo' => true]);
    Product::factory()->count(2)->create(['activo' => false]);

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 3)
        );
});

test('catalog index filters products by search term', function () {
    Product::factory()->create(['nombre' => 'Laptop Gamer Ultra', 'activo' => true]);
    Product::factory()->create(['nombre' => 'Mouse Inalambrico', 'activo' => true]);
    Product::factory()->create(['nombre' => 'Teclado Mecanico', 'activo' => true]);

    $this->get(route('catalog.index', ['q' => 'Mouse']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
        );
});

test('catalog index filters by SKU code', function () {
    Product::factory()->create(['codigo' => 'SKU-ESPECIAL-001', 'activo' => true]);
    Product::factory()->count(3)->create(['activo' => true]);

    $this->get(route('catalog.index', ['q' => 'SKU-ESPECIAL-001']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
        );
});

test('catalog index filters by category slug', function () {
    $cat = Category::factory()->create(['activo' => true]);
    $other = Category::factory()->create(['activo' => true]);

    Product::factory()->count(3)->create(['categoria_id' => $cat->id, 'activo' => true]);
    Product::factory()->count(2)->create(['categoria_id' => $other->id, 'activo' => true]);

    Cache::flush();

    $this->get(route('catalog.index', ['category' => $cat->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 3)
        );
});

test('catalog index includes subcategory products when filtering by parent', function () {
    $parent = Category::factory()->create(['activo' => true]);
    $child = Category::factory()->create([
        'categoria_padre_id' => $parent->id,
        'activo' => true,
    ]);
    $other = Category::factory()->create(['activo' => true]);

    Product::factory()->count(2)->create(['categoria_id' => $parent->id, 'activo' => true]);
    Product::factory()->count(3)->create(['categoria_id' => $child->id, 'activo' => true]);
    Product::factory()->count(1)->create(['categoria_id' => $other->id, 'activo' => true]);

    Cache::flush();

    $this->get(route('catalog.index', ['category' => $parent->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 5)
        );
});

test('catalog index filters by brand slug', function () {
    $brand = Brand::factory()->create(['activo' => true]);
    $other = Brand::factory()->create(['activo' => true]);

    Product::factory()->count(4)->create(['marca_id' => $brand->id, 'activo' => true]);
    Product::factory()->count(2)->create(['marca_id' => $other->id, 'activo' => true]);

    Cache::flush();

    $this->get(route('catalog.index', ['brand' => $brand->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 4)
        );
});

test('catalog index filters by minimum price', function () {
    Product::factory()->create(['precio' => 50.00, 'activo' => true]);
    Product::factory()->create(['precio' => 150.00, 'activo' => true]);
    Product::factory()->create(['precio' => 300.00, 'activo' => true]);

    $this->get(route('catalog.index', ['min_price' => 100]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 2)
        );
});

test('catalog index filters by maximum price', function () {
    Product::factory()->create(['precio' => 50.00, 'activo' => true]);
    Product::factory()->create(['precio' => 150.00, 'activo' => true]);
    Product::factory()->create(['precio' => 300.00, 'activo' => true]);

    $this->get(route('catalog.index', ['max_price' => 200]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 2)
        );
});

test('catalog index filters in-stock products', function () {
    Product::factory()->count(3)->create(['stock' => 10, 'activo' => true]);
    Product::factory()->count(2)->create(['stock' => 0, 'activo' => true]);

    $this->get(route('catalog.index', ['in_stock' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 3)
        );
});

test('catalog index sets noindex when search filter is active', function () {
    $this->get(route('catalog.index', ['q' => 'laptop']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.noindex', true)
        );
});

test('catalog index sets noindex when category filter is active', function () {
    $cat = Category::factory()->create();
    Cache::flush();

    $this->get(route('catalog.index', ['category' => $cat->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.noindex', true)
        );
});

test('catalog index does not set noindex on clean listing', function () {
    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.noindex', false)
        );
});

test('catalog index canonical always points to unfiltered url', function () {
    $this->get(route('catalog.index', ['q' => 'test', 'sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', url('/catalogo'))
        );
});

// ─── CATALOG SHOW ─────────────────────────────────────────────────────────────

test('catalog show renders product page', function () {
    $product = Product::factory()->create(['activo' => true]);

    $this->get(route('catalog.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Show')
            ->has('product')
            ->has('related')
            ->has('seo')
            ->where('product.slug', $product->slug)
        );
});

test('catalog show returns 404 for inactive product', function () {
    $product = Product::factory()->create(['activo' => false]);

    $this->get(route('catalog.show', $product->slug))
        ->assertNotFound();
});

test('catalog show returns 404 for nonexistent slug', function () {
    $this->get(route('catalog.show', 'producto-que-no-existe'))
        ->assertNotFound();
});

test('catalog show increments view count on first visit', function () {
    $product = Product::factory()->create(['activo' => true, 'visitas' => 0]);

    $this->get(route('catalog.show', $product->slug))->assertOk();

    expect($product->fresh()->visitas)->toBe(1);
    expect(ProductView::where('producto_id', $product->id)->count())->toBe(1);
});

test('catalog show does not double count views in the same session', function () {
    $product = Product::factory()->create(['activo' => true, 'visitas' => 0]);

    // Primera visita
    $this->get(route('catalog.show', $product->slug))->assertOk();
    // Segunda visita en la misma sesión
    $this->get(route('catalog.show', $product->slug))->assertOk();

    expect($product->fresh()->visitas)->toBe(1);
    expect(ProductView::where('producto_id', $product->id)->count())->toBe(1);
});

test('catalog show seo has product name as title', function () {
    $product = Product::factory()->create(['nombre' => 'Laptop Gaming XZ', 'activo' => true]);

    $this->get(route('catalog.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.title', 'Laptop Gaming XZ')
            ->where('seo.og_type', 'product')
        );
});

test('catalog show seo canonical matches product url', function () {
    $product = Product::factory()->create(['activo' => true]);

    $this->get(route('catalog.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', url('/catalogo/'.$product->slug))
        );
});

test('catalog show includes json-ld structured data', function () {
    $product = Product::factory()->create(['activo' => true]);

    $this->get(route('catalog.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('seo.json_ld')
            ->where('seo.json_ld.@type', 'Product')
            ->where('seo.json_ld.@context', 'https://schema.org')
        );
});

test('catalog show returns related products from same category', function () {
    $category = Category::factory()->create();

    $product = Product::factory()->create(['categoria_id' => $category->id, 'activo' => true]);
    Product::factory()->count(3)->create(['categoria_id' => $category->id, 'activo' => true]);

    Cache::flush();

    $this->get(route('catalog.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('related', 3)
        );
});
