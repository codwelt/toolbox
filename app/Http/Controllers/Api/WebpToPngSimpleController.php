<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WebpToPngSimpleController extends Controller
{
    public function convert(Request $request)
    {
        $allowedFormats = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'tiff'];
        $request->validate([
            'target_format' => 'nullable|in:' . implode(',', $allowedFormats),
            'image' => 'required|file|mimetypes:image/jpeg,image/png,image/webp,image/gif,image/bmp,image/tiff,image/svg+xml|max:5120', // 5MB
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        // Guardar temporalmente el archivo
        $file = $request->file('image');
        $tempRelative = $file->store('tmp/image-converter');
        $inputPath = Storage::path($tempRelative);
        $targetFormat = strtolower($request->input('target_format', 'png'));

        try {
            // Cargar imagen y convertir
            $imagick = new \Imagick($inputPath);
            $imagick->setImageFormat($targetFormat === 'jpg' ? 'jpeg' : $targetFormat);

            $convertedBlob = $imagick->getImagesBlob();

            $imagick->clear();
            $imagick->destroy();

            // Nombre sugerido para descarga
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = $baseName . '.' . $targetFormat;

            return response($convertedBlob, 200)
                ->header('Content-Type', $this->mimeFromFormat($targetFormat))
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Throwable $e) {
            Log::error('Error al convertir imagen (upload)', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Ocurrió un error al convertir la imagen. Asegúrate de que el formato sea compatible.',
            ], 500);
        } finally {
            Storage::delete($tempRelative);
        }
    }


    public function convertFromUrl(Request $request)
    {
        $allowedFormats = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'tiff'];
        $request->validate([
            'image_url' => 'required|url',
            'target_format' => 'nullable|in:' . implode(',', $allowedFormats),
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        $response = \Illuminate\Support\Facades\Http::get($request->image_url);

        if (
            !$response->ok() ||
            !str_starts_with(strtolower($response->header('Content-Type', '')), 'image/')
        ) {
            return response()->json([
                'message' => 'No se pudo descargar una imagen válida desde la URL proporcionada.',
            ], 422);
        }

        $targetFormat = strtolower($request->input('target_format', 'png'));
        $extension = pathinfo(parse_url($request->image_url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $extension = $extension ?: 'img';

        $filename = 'url_' . uniqid() . '.' . $extension;
        $tempRelative = 'tmp/image-converter/' . $filename;
        Storage::put($tempRelative, $response->body());
        $inputPath = Storage::path($tempRelative);

        try {
            $imagick = new \Imagick($inputPath);
            $imagick->setImageFormat($targetFormat === 'jpg' ? 'jpeg' : $targetFormat);
            $convertedBlob = $imagick->getImagesBlob();
            $imagick->clear();
            $imagick->destroy();

            return response($convertedBlob, 200)
                ->header('Content-Type', $this->mimeFromFormat($targetFormat))
                ->header('Content-Disposition', 'attachment; filename="imagen_convertida.' . $targetFormat . '"');
        } catch (\Throwable $e) {
            Log::error('Error al convertir imagen (url)', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Ocurrió un error al convertir la imagen. Asegúrate de que el formato sea compatible.',
            ], 500);
        } finally {
            Storage::delete($tempRelative);
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
