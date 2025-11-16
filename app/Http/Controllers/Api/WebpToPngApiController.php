<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WebpToPngApiController extends Controller
{
    public function process(Request $request)
    {
        $request->validate([
            'source_type' => 'required|in:file,url',
            'image' => 'required_if:source_type,file|file|mimetypes:image/webp|max:5120',
            'image_url' => 'required_if:source_type,url|url',
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        $tempRelative = null;

        try {
            if ($request->input('source_type') === 'file') {
                $file = $request->file('image');
                $tempRelative = $file->store('tmp/webp-to-png');
            } else {
                // source_type = url
                $imageUrl = $request->input('image_url');

                $response = Http::get($imageUrl);

                if (!$response->ok()) {
                    return response()->json([
                        'message' => 'No se pudo descargar la imagen desde la URL proporcionada.',
                    ], 422);
                }

                // Guardamos contenido como .webp temporal
                $filename = 'url_' . uniqid() . '.webp';
                $tempRelative = 'tmp/webp-to-png/' . $filename;
                Storage::put($tempRelative, $response->body());
            }

            $inputPath = Storage::path($tempRelative);

            // Cargar con Imagick y convertir a PNG
            $imagick = new \Imagick($inputPath);
            $imagick->setImageFormat('png');

            $pngBlob = $imagick->getImagesBlob();
            $imagick->clear();
            $imagick->destroy();

            return response($pngBlob, 200)
                ->header('Content-Type', 'image/png');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error al convertir la imagen.',
            ], 500);
        } finally {
            if ($tempRelative) {
                Storage::delete($tempRelative);
            }
        }
    }
}
