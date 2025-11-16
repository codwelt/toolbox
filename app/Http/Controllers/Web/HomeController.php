<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $toolsConfig = config('tools') ?? [];

        $tools = collect($toolsConfig)
            ->map(function ($tool, $key) {
                // Solo tools que tengan path y title
                if (!isset($tool['path'], $tool['title'])) {
                    return null;
                }

                return [
                    'key' => $key,
                    'title' => $tool['title'],
                    'description' => $tool['description'] ?? null,
                    'path' => $tool['path'],
                    'canonical' => $tool['canonical'] ?? url($tool['path']),
                    'h1' => $tool['h1'] ?? null,
                ];
            })
            ->filter()
            ->values();

        $seo = [
            'title' => 'Toolbox Codwelt | Herramientas online gratuitas para imágenes y recursos digitales',
            'description' => 'Suite de herramientas online gratuitas de Codwelt para comprimir imágenes, redimensionar, quitar fondo, convertir formatos, poner marcas de agua y más.',
            'canonical' => url('/'),
        ];

        return Inertia::render('Home/Index', [
            'seo' => $seo,
            'tools' => $tools,
        ]);
    }
}
