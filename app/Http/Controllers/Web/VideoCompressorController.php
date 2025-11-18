<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VideoCompressorController extends Controller
{
    public function index(Request $request)
    {
        $tool = config('tools.categories.videos.items.video_compressor');

        $seo = [
            'title'       => $tool['title'],
            'description' => $tool['description'],
            'canonical'   => $tool['canonical'],
            'keywords'    => $tool['keywords'],
            'h1'          => $tool['h1'],
            'faq'         => $tool['faq'],
            'url'         => $tool['canonical'],
        ];

        // Presets recomendados (para mostrarlos en la vista)
        $presets = [
            [
                'key'              => 'whatsapp',
                'label'            => 'WhatsApp / Telegram (bajo peso)',
                'compression'      => 60,   // % de compresión aprox.
                'max_width'        => 960,
                'max_height'       => 540,
            ],
            [
                'key'              => 'social_hd',
                'label'            => 'Redes sociales HD (1080p)',
                'compression'      => 40,
                'max_width'        => 1920,
                'max_height'       => 1080,
            ],
            [
                'key'              => 'stories',
                'label'            => 'Historias / Reels (vertical)',
                'compression'      => 50,
                'max_width'        => 1080,
                'max_height'       => 1920,
            ],
            [
                'key'              => 'youtube',
                'label'            => 'YouTube / Web estándar',
                'compression'      => 35,
                'max_width'        => 1920,
                'max_height'       => 1080,
            ],
        ];

        return Inertia::render('VideoCompressor/Index', [
            'seo'     => $seo,
            'presets' => $presets,
        ]);
    }
}
