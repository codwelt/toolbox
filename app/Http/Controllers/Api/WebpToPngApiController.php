<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WebpToPngApiController extends Controller
{
    public function process(Request $request)
    {
        $allowedFormats = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'tiff'];

        $request->validate([
            'source_type' => 'required|in:file,url',
            'target_format' => 'nullable|in:' . implode(',', $allowedFormats),
            'image' => 'required_if:source_type,file|file|mimetypes:image/jpeg,image/png,image/webp,image/gif,image/bmp,image/tiff,image/svg+xml|max:5120',
            'image_url' => 'required_if:source_type,url|url',
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        $targetFormat = strtolower($request->input('target_format', 'png'));
        $tempRelative = null;

        try {
            if ($request->input('source_type') === 'file') {
                $file = $request->file('image');
                $tempRelative = $file->store('tmp/image-converter');
            } else {
                // source_type = url
                $imageUrl = $request->input('image_url');

                $response = Http::get($imageUrl);

                if (
                    !$response->ok() ||
                    !str_starts_with(strtolower($response->header('Content-Type', '')), 'image/')
                ) {
                    return response()->json([
                        'message' => 'No se pudo descargar una imagen válida desde la URL proporcionada.',
                    ], 422);
                }

                $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
                $extension = $extension ?: 'img';

                $filename = 'url_' . uniqid() . '.' . $extension;
                $tempRelative = 'tmp/image-converter/' . $filename;
                Storage::put($tempRelative, $response->body());
            }

            $inputPath = Storage::path($tempRelative);

            // Cargar con Imagick y convertir a PNG
            $imagick = new \Imagick($inputPath);
            $imagick->setImageFormat($targetFormat === 'jpg' ? 'jpeg' : $targetFormat);

            $convertedBlob = $imagick->getImagesBlob();
            $imagick->clear();
            $imagick->destroy();

            return response($convertedBlob, 200)
                ->header('Content-Type', $this->mimeFromFormat($targetFormat));
        } catch (\Throwable $e) {
            Log::error('Error al convertir imagen', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Ocurrió un error al convertir la imagen. Asegúrate de que el formato sea compatible.',
            ], 500);
        } finally {
            if ($tempRelative) {
                Storage::delete($tempRelative);
            }
        }
    }

    private function mimeFromFormat(string $format): string
    {
        return match ($format) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            default => 'application/octet-stream',
        };
    }
}
