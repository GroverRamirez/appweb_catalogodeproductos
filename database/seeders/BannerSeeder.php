<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            [
                'titulo' => 'Nuevos productos cada semana',
                'subtitulo' => 'Descubre lo ultimo en tecnologia, hogar y mas.',
                'imagen' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1800&h=700&q=85',
                'enlace' => '/catalogo?sort=newest',
                'texto_cta' => 'Ver novedades',
                'orden' => 1,
                'activo' => true,
            ],
            [
                'titulo' => 'Ofertas que no puedes perderte',
                'subtitulo' => 'Productos seleccionados con descuento.',
                'imagen' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1800&h=700&q=85',
                'enlace' => '/catalogo?sort=discount',
                'texto_cta' => 'Ver ofertas',
                'orden' => 2,
                'activo' => true,
            ],
            [
                'titulo' => 'Consulta por WhatsApp',
                'subtitulo' => 'Atencion personalizada para tu pedido.',
                'imagen' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1800&h=700&q=85',
                'enlace' => '/catalogo',
                'texto_cta' => 'Explorar catalogo',
                'orden' => 3,
                'activo' => true,
            ],
        ];

        foreach ($banners as $bannerData) {
            $banner = Banner::query()->where('titulo', $bannerData['titulo'])->first();

            if ($banner) {
                $banner->update($bannerData);

                continue;
            }

            Banner::create($bannerData);
        }
    }
}
