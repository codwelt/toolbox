<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {

            $urls = [];

            // 1) Home
            $urls[] = [
                'loc'        => URL::to('/'),
                'lastmod'    => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority'   => '1.0',
            ];

            // 2) (Opcional) página de listado de herramientas si la creas
            /*
            $urls[] = [
                'loc'        => URL::to('/herramientas'),
                'lastmod'    => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority'   => '0.9',
            ];
            */

            // 3) Recorrer categorías y sus herramientas desde config/tools.php
            $categories = config('tools.categories', []);

            foreach ($categories as $categoryKey => $category) {

                $items = $category['items'] ?? [];

                foreach ($items as $toolKey => $tool) {
                    $loc = null;

                    // 1. Si tiene route y existe, usamos route()
                    if (!empty($tool['route']) && \Route::has($tool['route'])) {
                        $loc = route($tool['route']);
                    }
                    // 2. Si no, usamos el path configurado
                    elseif (!empty($tool['path'])) {
                        $path = ltrim($tool['path'], '/');
                        $loc = URL::to($path);
                    }
                    // 3. Último recurso: canonical completa
                    elseif (!empty($tool['canonical'])) {
                        $loc = $tool['canonical'];
                    }

                    if ($loc) {
                        $urls[] = [
                            'loc'        => $loc,
                            'lastmod'    => now()->toAtomString(),
                            // Permite override desde el tool o la categoría, con fallback
                            'changefreq' => $tool['changefreq'] ?? ($category['changefreq'] ?? 'weekly'),
                            'priority'   => $tool['priority'] ?? ($category['priority'] ?? '0.8'),
                        ];
                    }
                }
            }

            return view('sitemap.index', compact('urls'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
