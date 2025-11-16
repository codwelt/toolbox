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

            // 2) Página de listado de herramientas (cuando la tengas)
            // Ajusta la ruta o coméntala si aún no existe
          /**  $urls[] = [
                'loc'        => URL::to('/herramientas'),
                'lastmod'    => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority'   => '0.9',
            ]; */

            // 3) Cada herramienta definida en config/tools.php
            $tools = config('tools', []);

            foreach ($tools as $key => $tool) {
                // Intentamos primero por nombre de ruta
                $loc = null;

                if (!empty($tool['route']) && \Route::has($tool['route'])) {
                    $loc = route($tool['route']);
                } elseif (!empty($tool['path'])) {
                    // Fallback al path definido en config (e.g. /comprimir-imagenes-online-gratis)
                    $path = ltrim($tool['path'], '/');
                    $loc = URL::to($path);
                } elseif (!empty($tool['canonical'])) {
                    // Última opción: usar la canonical si viene completa
                    $loc = $tool['canonical'];
                }

                if ($loc) {
                    $urls[] = [
                        'loc'        => $loc,
                        'lastmod'    => now()->toAtomString(), // si luego añades 'lastmod' al config, lo usamos
                        'changefreq' => 'weekly',
                        'priority'   => '0.8',
                    ];
                }
            }

            return view('sitemap.index', compact('urls'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
