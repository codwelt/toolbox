<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmojiLibraryController extends Controller
{
    public function index(Request $request)
    {
        
        $tool = config('tools.categories.herramientas.items.emoji_library');
        
        $seo = [
            'title' => $tool['title'],
            'description' => $tool['description'],
            'canonical' => $tool['canonical'],
            'keywords' => $tool['keywords'],
            'h1' => $tool['h1'],
            'faq' => $tool['faq'],
            'url' => $tool['canonical'],
        ];

        return Inertia::render('EmojiLibrary/Index', [
            'seo' => $seo,
        ]);
    }
}
