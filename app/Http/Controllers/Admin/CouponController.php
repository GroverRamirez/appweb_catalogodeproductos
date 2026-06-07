<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $coupons = Coupon::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('codigo', 'like', "%$term%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/coupons/Index', [
            'coupons' => $coupons,
            'filters' => $request->only(['q']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/Form', ['coupon' => null]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        Coupon::create([...$request->validated(), 'code' => strtoupper($request->input('code'))]);

        return to_route('admin.coupons.index')->with('success', 'Cupón creado.');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/Form', ['coupon' => $coupon]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update([...$request->validated(), 'code' => strtoupper($request->input('code'))]);

        return to_route('admin.coupons.index')->with('success', 'Cupón actualizado.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return to_route('admin.coupons.index')->with('success', 'Cupón eliminado.');
    }
}
