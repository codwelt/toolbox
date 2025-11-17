<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WatermarkController extends Controller
{
    public function index(Request $request)
    {
        $tool = config('tools.categories.images.items.watermark_image');

        $seo = [
            'title' => $tool['title'],
            'description' => $tool['description'],
            'canonical' => $tool['canonical'],
            'keywords' => $tool['keywords'],
            'h1' => $tool['h1'],
            'faq' => $tool['faq'],
            'url' => $tool['canonical'],
        ];

        return Inertia::render('Watermark/Index', [
            'seo' => $seo,
        ]);
    }
}
