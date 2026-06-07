<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    protected function assetUrl(mixed $path): ?string
    {
        if (! $path) {
            return null;
        }
        $str = (string) $path;

        return str_starts_with($str, 'http') ? $str : Storage::url($str);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user?->getRoleNames() ?? [],
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name') ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => App::getLocale(),
            'translations' => fn () => trans('catalog'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'store' => fn () => [
                'name' => Setting::get('store_name', 'Mi Catálogo'),
                'tagline' => Setting::get('store_tagline', ''),
                'whatsapp' => Setting::get('whatsapp_number', ''),
                'whatsapp_template' => Setting::get(
                    'whatsapp_message_template',
                    'Hola, me interesa el producto: {producto} (código {codigo}).'
                ),
                'email' => Setting::get('email_contact', ''),
                'address' => Setting::get('address', ''),
                'hours' => Setting::get('business_hours', ''),
                'currency' => Setting::get('currency', 'PEN'),
                'currency_symbol' => Setting::get('currency_symbol', 'S/'),
                'show_prices' => (bool) Setting::get('show_prices', true),
                'show_stock' => (bool) Setting::get('show_stock', true),
                'logo_url' => $this->assetUrl(Setting::get('logo_path')),
                'favicon_url' => $this->assetUrl(Setting::get('favicon_path')),
            ],
        ];
    }
}
