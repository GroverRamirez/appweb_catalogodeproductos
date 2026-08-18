<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('nombre', 'like', "%$term%"))
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('activo', $request->boolean('status'));
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/suppliers/Form', ['supplier' => null]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return to_route('admin.suppliers.index')->with('success', 'Proveedor creado.');
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('admin/suppliers/Form', ['supplier' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return to_route('admin.suppliers.index')->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return to_route('admin.suppliers.index')->with('success', 'Proveedor eliminado.');
    }
}
