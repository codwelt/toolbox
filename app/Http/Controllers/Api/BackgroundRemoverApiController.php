<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SimpleBackgroundRemover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackgroundRemoverApiController extends Controller
{
    public function process(Request $request, SimpleBackgroundRemover $remover)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB
            'size_mode' => 'required|in:original,preset,custom',
            'preset' => 'nullable|in:small,medium,large',
            'custom_width' => 'nullable|integer|min:50|max:8000',
            'custom_height' => 'nullable|integer|min:50|max:8000',
        ]);

        $file = $request->file('image');
        $sizeMode = $request->input('size_mode');
        $preset = $request->input('preset');
        $customWidth = $request->input('custom_width');
        $customHeight = $request->input('custom_height');

        // Guardamos original temporalmente en storage
        $tempRelative = $file->store('tmp/background-remover/original');
        $inputPath = Storage::path($tempRelative);

        // Definimos ruta de salida temporal
        $outputRelative = 'tmp/background-remover/output_' . uniqid() . '.png';
        $outputPath = Storage::path($outputRelative);

        try {
            // Procesamos con nuestro service
            $remover->process(
                $inputPath,
                $outputPath,
                $sizeMode,
                $preset,
                $customWidth ? (int) $customWidth : null,
                $customHeight ? (int) $customHeight : null
            );

            // Leemos el PNG generado
            $imageContent = Storage::get($outputRelative);
            $mimeType = 'image/png';

            // Respondemos como blob
            return response($imageContent, 200)
                ->header('Content-Type', $mimeType);
        } catch (\RuntimeException $e) {
            // Caso típico: Imagick no instalado
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error al procesar la imagen.',
            ], 500);
        } finally {
            // Limpieza de archivos temporales
            Storage::delete($tempRelative);
            Storage::delete($outputRelative);
        }
    }
}
