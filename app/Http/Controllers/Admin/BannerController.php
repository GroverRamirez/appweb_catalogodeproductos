<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Models\Banner;
use App\Services\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function __construct(private readonly ImageProcessor $images) {}

    public function index(Request $request): Response
    {
        $banners = Banner::query()
            ->orderBy('orden')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/banners/Index', [
            'banners' => $banners,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/banners/Form', ['banner' => null]);
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['image'] = $this->resolveImage($request, null);

        Banner::create($data);

        return to_route('admin.banners.index')->with('success', 'Banner creado.');
    }

    public function edit(Banner $banner): Response
    {
        return Inertia::render('admin/banners/Form', ['banner' => $banner]);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): RedirectResponse
    {
        $data = $request->validated();
        $newImage = $this->resolveImage($request, $banner);
        if ($newImage) {
            $data['image'] = $newImage;
        } else {
            unset($data['image']);
        }

        $banner->update($data);

        return to_route('admin.banners.index')->with('success', 'Banner actualizado.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->images->delete($banner->image ?? '');
        $banner->delete();

        return to_route('admin.banners.index')->with('success', 'Banner eliminado.');
    }

    /**
     * Devuelve el path a guardar:
     *  - si subió archivo nuevo: optimiza/convierte a WebP y borra el anterior
     *  - si pasó image_url (string HTTP): la usa tal cual
     *  - si no: null (sin cambio)
     */
    protected function resolveImage(Request $request, ?Banner $banner): ?string
    {
        if ($request->hasFile('image_file')) {
            // Borrar imagen previa del disco
            if ($banner) {
                $this->images->delete($banner->image ?? '');
            }

            return $this->images->banner($request->file('image_file'));
        }

        if ($request->filled('image_url')) {
            return (string) $request->input('image_url');
        }

        return null;
    }
}
