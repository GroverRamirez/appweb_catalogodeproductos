<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Catalog\CartValidationController;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Catalog\InquiryController as PublicInquiryController;
use App\Http\Controllers\Catalog\SitemapController;
use App\Http\Controllers\LocaleController;
use App\Models\Inquiry;
use App\Support\AuthRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// === SEO ===
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// === Catálogo público ===
Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{slug}', [CatalogController::class, 'show'])->name('catalog.show');

// Carrito + checkout
Route::inertia('/carrito', 'catalog/Cart')->name('cart.index');

// Favoritos (la lista vive en el localStorage del navegador)
Route::inertia('/favoritos', 'catalog/Wishlist')->name('wishlist.index');
Route::get('/carrito/gracias/{inquiry:public_token}', function (Inquiry $inquiry) {
    $inquiry->load('items:id,consulta_id,producto_nombre_copia,producto_codigo_copia,cantidad,precio_unitario');

    return inertia('catalog/CartThanks', [
        'inquiry' => [
            'id' => $inquiry->id,
            'total_estimated' => $inquiry->total_estimated,
            'items' => $inquiry->items,
        ],
    ]);
})->middleware('signed')->name('cart.thanks');

// Endpoints públicos para consultas
Route::post('/consultas', [PublicInquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('public.inquiries.store');

Route::post('/checkout', [PublicInquiryController::class, 'checkout'])
    ->middleware('throttle:10,1')
    ->name('public.checkout');

Route::post('/cupones/validar', [PublicInquiryController::class, 'validateCoupon'])
    ->middleware('throttle:30,1')
    ->name('public.coupons.validate');

Route::post('/carrito/validar', CartValidationController::class)
    ->middleware('throttle:20,1')
    ->name('public.cart.validate');

// Multi-idioma
Route::get('/locale/{locale}', [LocaleController::class, 'set'])->name('locale.set');

// Login con Google (solo clientes; el staff siempre usa contraseña)
Route::middleware('guest')->group(function () {
    Route::get('/login/google', [GoogleAuthController::class, 'redirect'])->name('login.google');
    Route::get('/login/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('login.google.callback');
});

// Dashboard del starter -> redirige según el rol
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn (Request $r) => redirect(AuthRedirect::for($r->user())))
        ->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
