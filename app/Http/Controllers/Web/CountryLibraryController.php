<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;

class CountryLibraryController extends Controller
{
    public function index()
    {
        // Tomamos la config SEO desde config/tools.php
        $tool = config('tools.categories.utilities.items.country_library');

        $seo = [
            'title' => $tool['title'],
            'description' => $tool['description'],
            'canonical' => $tool['canonical'],
            'keywords' => $tool['keywords'],
            'h1' => $tool['h1'],
            'faq' => $tool['faq'],
            'url' => $tool['canonical'],
        ];
        return Inertia::render('CountryLibrary/Index', [
            'seo' => $seo,
        ]);
    }
}
