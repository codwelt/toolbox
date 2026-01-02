<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HeadingStructureAnalyzerService;
use Illuminate\Http\Request;

class SeoHeadingStructureApiController extends Controller
{
    public function __construct(private HeadingStructureAnalyzerService $analyzer)
    {
    }

    public function analyze(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'string'],
        ]);

        try {
            $result = $this->analyzer->analyze($validated['url']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => 'URL inválida.'], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'No pudimos leer la página.'], 500);
        }

        return response()->json($result);
    }
}
