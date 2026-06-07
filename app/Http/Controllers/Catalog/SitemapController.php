<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Serve /sitemap.xml
     *
     * Cached for 1 hour. The TTL-based expiry is sufficient — search bots
     * typically re-fetch sitemaps at most once or twice a day.
     */
    public function __invoke(): Response
    {
        $xml = Cache::remember('catalog.sitemap', now()->addHour(), function () {
            return $this->build();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    private function build(): string
    {
        $urls = collect();

        // Static pages
        $urls->push([
            'loc'        => url('/'),
            'changefreq' => 'daily',
            'priority'   => '1.0',
        ]);

        $urls->push([
            'loc'        => url('/catalogo'),
            'changefreq' => 'daily',
            'priority'   => '0.9',
        ]);

        // Categories — each filter page (/catalogo?category=slug)
        Category::query()
            ->active()
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function ($cat) use ($urls) {
                $urls->push([
                    'loc'        => url('/catalogo').'?category='.$cat->slug,
                    'lastmod'    => $cat->updated_at->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.7',
                ]);
            });

        // Products
        Product::query()
            ->active()
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function ($product) use ($urls) {
                $urls->push([
                    'loc'        => url('/catalogo/'.$product->slug),
                    'lastmod'    => $product->updated_at->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                ]);
            });

        return $this->render($urls->all());
    }

    private function render(array $urls): string
    {
        $items = '';
        foreach ($urls as $u) {
            $items .= "\n    <url>";
            $items .= "\n        <loc>".e($u['loc']).'</loc>';
            if (isset($u['lastmod'])) {
                $items .= "\n        <lastmod>{$u['lastmod']}</lastmod>";
            }
            if (isset($u['changefreq'])) {
                $items .= "\n        <changefreq>{$u['changefreq']}</changefreq>";
            }
            if (isset($u['priority'])) {
                $items .= "\n        <priority>{$u['priority']}</priority>";
            }
            $items .= "\n    </url>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'.
            "\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.
            $items.
            "\n</urlset>\n";
    }
}
