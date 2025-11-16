<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebpToPngSimpleController extends Controller
{
    public function convert(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimetypes:image/webp|max:5120', // 5MB
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        // Guardar temporalmente el archivo .webp
        $file = $request->file('image');
        $tempRelative = $file->store('tmp/webp-to-png');
        $inputPath = Storage::path($tempRelative);

        try {
            // Cargar WebP y convertir a PNG
            $imagick = new \Imagick($inputPath);
            $imagick->setImageFormat('png');

            $pngBlob = $imagick->getImagesBlob();

            $imagick->clear();
            $imagick->destroy();

            // Nombre sugerido para descarga
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = $baseName . '.png';

            return response($pngBlob, 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error al convertir la imagen.',
            ], 500);
        } finally {
            Storage::delete($tempRelative);
        }
    }


    public function convertFromUrl(Request $request)
    {
        $request->validate([
            'image_url' => 'required|url',
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        $response = \Illuminate\Support\Facades\Http::get($request->image_url);

        if (!$response->ok()) {
            return response()->json([
                'message' => 'No se pudo descargar la imagen desde la URL proporcionada.',
            ], 422);
        }

        $filename = 'url_' . uniqid() . '.webp';
        $tempRelative = 'tmp/webp-to-png/' . $filename;
        Storage::put($tempRelative, $response->body());
        $inputPath = Storage::path($tempRelative);

        try {
            $imagick = new \Imagick($inputPath);
            $imagick->setImageFormat('png');
            $pngBlob = $imagick->getImagesBlob();
            $imagick->clear();
            $imagick->destroy();

            return response($pngBlob, 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="imagen_convertida.png"');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error al convertir la imagen.',
            ], 500);
        } finally {
            Storage::delete($tempRelative);
        }
    }

}
