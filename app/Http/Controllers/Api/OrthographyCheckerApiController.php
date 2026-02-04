<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrthographyCheckerService;
use Illuminate\Http\Request;

class OrthographyCheckerApiController extends Controller
{
    public function __construct(private OrthographyCheckerService $checker)
    {
    }

    public function check(Request $request)
    {
        $validated = $request->validate([
            'text' => ['sometimes', 'string'],
            'url' => ['sometimes', 'string'],
        ]);

        if (empty($validated['text']) && empty($validated['url'])) {
            return response()->json(['message' => 'Proporciona texto o una URL.'], 422);
        }

        try {
            $result = $this->checker->analyze($validated['text'] ?? null, $validated['url'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'No pudimos analizar la ortografía.'], 500);
        }

        return response()->json($result);
    }
}
