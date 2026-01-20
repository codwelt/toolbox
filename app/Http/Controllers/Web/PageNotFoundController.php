<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PageNotFoundController
{
    public function __invoke(Request $request)
    {
        $categories = config('tools.categories', []);

        $tools = collect($categories)
            ->flatMap(function ($category, $categoryKey) {
                $items = collect($category['items'] ?? []);

                return $items->map(function ($tool, $toolKey) use ($categoryKey, $category) {
                    $path = null;

                    if (!empty($tool['route']) && Route::has($tool['route'])) {
                        $path = route($tool['route']);
                    } elseif (!empty($tool['path'])) {
                        $path = url($tool['path']);
                    } elseif (!empty($tool['canonical'])) {
                        $path = $tool['canonical'];
                    }

                    return [
                        'key' => $categoryKey . '.' . $toolKey,
                        'name' => $tool['h1'] ?? $tool['title'] ?? ucfirst(str_replace('_', ' ', $toolKey)),
                        'description' => $tool['description'] ?? '',
                        'path' => $path,
                        'category' => [
                            'key' => $categoryKey,
                            'label' => $category['label'] ?? ucfirst($categoryKey),
                        ],
                    ];
                });
            })
            ->filter(fn ($tool) => !empty($tool['path']))
            ->values()
            ->all();

        return Inertia::render('NotFound', [
            'toolList' => $tools,
        ])->toResponse($request)->setStatusCode(404);
    }
}
