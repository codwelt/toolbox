<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JsonFormatterController extends Controller
{
    public function index(Request $request)
    {
        $tool = config('tools.categories.herramientas.items.json_formatter');

        $seo = [
            'title' => $tool['title'],
            'description' => $tool['description'],
            'canonical' => $tool['canonical'],
            'keywords' => $tool['keywords'],
            'h1' => $tool['h1'],
            'faq' => $tool['faq'],
            'url' => $tool['canonical'],
        ];

        return Inertia::render('JsonFormatter/Index', [
            'seo' => $seo,
        ]);
    }
}
