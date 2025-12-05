<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiWebsiteDetectorService;
use Illuminate\Http\Request;

class AiWebsiteDetectorApiController extends Controller
{
    public function __construct(private AiWebsiteDetectorService $detector)
    {
    }

    public function analyze(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'string'],
        ]);

        try {
            $result = $this->detector->analyze($validated['url']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => 'URL inválida.'], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'No pudimos leer la página.'], 500);
        }

        return response()->json($result);
    }
}
