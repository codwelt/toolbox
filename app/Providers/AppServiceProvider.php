<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Inertia::share('toolCategories', function () {
            $categories = config('tools.categories', []);

            return collect($categories)->map(function ($category, $categoryKey) {
                $items = collect($category['items'] ?? []);

                return [
                    'key' => $categoryKey,
                    'label' => $category['label'] ?? ucfirst($categoryKey),
                    'items' => $items->map(function ($tool, $toolKey) {
                        return [
                            'key' => $toolKey,
                            'name' => $tool['h1'] ?? $tool['title'] ?? ucfirst(str_replace('_', ' ', $toolKey)),
                            'path' => $tool['path'] ?? null,
                            'route' => $tool['route'] ?? null,
                        ];
                    })->values(),
                ];
            })->values();
        });
    }
}