<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->with('parent:id,nombre')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('nombre', 'like', "%$term%"))
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('activo', $request->boolean('status'));
            })
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/Form', [
            'category' => null,
            'parents' => Category::query()->whereNull('categoria_padre_id')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return to_route('admin.categories.index')->with('success', 'Categoría creada.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/Form', [
            'category' => $category,
            'parents' => Category::query()
                ->whereNull('categoria_padre_id')
                ->where('id', '!=', $category->id)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('admin.categories.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return to_route('admin.categories.index')->with('success', 'Categoría eliminada.');
    }
}
