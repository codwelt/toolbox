<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HtmlToTextController extends Controller
{
    public function index(Request $request)
    {
        $tool = config('tools.categories.herramientas.items.html_to_text');

        $seo = [
            'title' => $tool['title'],
            'description' => $tool['description'],
            'canonical' => $tool['canonical'],
            'keywords' => $tool['keywords'],
            'h1' => $tool['h1'],
            'faq' => $tool['faq'],
            'url' => $tool['canonical'],
        ];

        return Inertia::render('HtmlToText/Index', [
            'seo' => $seo,
        ]);
    }
}
