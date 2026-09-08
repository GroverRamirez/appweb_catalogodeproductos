<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Services\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ImageProcessor $images) {}

    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category:id,nombre', 'brand:id,nombre', 'mainImage'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('category'), fn ($q) => $q->where('categoria_id', $request->integer('category')))
            ->when($request->filled('brand'), fn ($q) => $q->where('marca_id', $request->integer('brand')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('activo', $request->boolean('status'));
            })
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock())
            ->when($request->boolean('no_cost'), fn ($q) => $q->withoutCost())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'filters' => $request->only(['q', 'category', 'brand', 'status', 'low_stock', 'no_cost']),
            'categories' => Category::query()->orderBy('nombre')->get(['id', 'nombre']),
            'brands' => Brand::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/Form', [
            'product' => null,
            'categories' => Category::query()->orderBy('nombre')->get(['id', 'nombre']),
            'brands' => Brand::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $product = Product::create(collect($data)->except(['images', 'attributes'])->all());

            $this->syncAttributes($product, $data['attributes'] ?? []);
            $this->syncImages($product, $request);
        });

        return to_route('admin.products.index')->with('success', 'Producto creado.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['images', 'attributes']);

        return Inertia::render('admin/products/Form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('nombre')->get(['id', 'nombre']),
            'brands' => Brand::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $product, $request) {
            $product->update(collect($data)->except(['images', 'attributes', 'remove_image_ids'])->all());

            $this->syncAttributes($product, $data['attributes'] ?? []);

            // Borrar imágenes marcadas (principal + thumbnail)
            if (! empty($data['remove_image_ids'])) {
                $toDelete = ProductImage::where('producto_id', $product->id)
                    ->whereIn('id', $data['remove_image_ids'])
                    ->get();

                foreach ($toDelete as $img) {
                    $this->images->delete(
                        $img->path ?? '',
                        $img->thumb_path,
                    );
                    $img->delete();
                }
            }

            $this->syncImages($product, $request);
        });

        return to_route('admin.products.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Eliminar todas las imágenes del producto (principal + thumbnails)
        foreach ($product->images as $img) {
            $this->images->delete($img->path ?? '', $img->thumb_path);
        }

        $product->delete();

        return to_route('admin.products.index')->with('success', 'Producto eliminado.');
    }

    protected function syncAttributes(Product $product, array $attributes): void
    {
        $product->attributes()->delete();
        foreach ($attributes as $i => $row) {
            if (empty($row['key']) || empty($row['value'])) {
                continue;
            }
            ProductAttribute::create([
                'product_id' => $product->id,
                'key' => $row['key'],
                'value' => $row['value'],
                'sort_order' => $i,
            ]);
        }
    }

    protected function syncImages(Product $product, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $existingCount = $product->images()->count();

        foreach ($request->file('images') as $i => $file) {
            // Procesar: resize + WebP + thumbnail
            ['path' => $path, 'thumb_path' => $thumbPath] = $this->images->product($file, $product->id);

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'thumb_path' => $thumbPath,
                'alt' => $product->name,
                'sort_order' => $existingCount + $i,
                'is_main' => $existingCount === 0 && $i === 0,
            ]);
        }
    }
}
