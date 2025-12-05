<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QrGeneratorController extends Controller
{
    public function index(Request $request)
    {
        $tool = config('tools.categories.images.items.qr_generator');

        $seo = [
            'title' => $tool['title'],
            'description' => $tool['description'],
            'canonical' => $tool['canonical'],
            'keywords' => $tool['keywords'],
            'h1' => $tool['h1'],
            'faq' => $tool['faq'],
            'url' => $tool['canonical'],
        ];

        return Inertia::render('QrGenerator/Index', [
            'seo' => $seo,
        ]);
    }
}
