<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Base64EncoderController extends Controller
{
    public function index(Request $request)
    {
        $tool = config('tools.categories.herramientas.items.base64_encoder');

        $seo = [
            'title' => $tool['title'],
            'description' => $tool['description'],
            'canonical' => $tool['canonical'],
            'keywords' => $tool['keywords'],
            'h1' => $tool['h1'],
            'faq' => $tool['faq'],
            'url' => $tool['canonical'],
        ];

        return Inertia::render('Base64Encoder/Index', [
            'seo' => $seo,
        ]);
    }
}
