<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class FaviconsApiController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        $file = $request->file('image');

        // Carpeta temporal única para este set de favicons
        $token = uniqid('fav_', true);
        $baseFolder = "public/favicons/{$token}";
        Storage::makeDirectory($baseFolder);

        $inputPath = $file->store("tmp/favicons_input");
        $inputAbs = Storage::path($inputPath);

        // Tamaños que vamos a generar
        $sizesPng = [
            16,
            32,
            48,
            96,
            180, // Apple touch icon
            192, // Android / PWA
        ];

        try {
            $imagesForIco = [];

            // Generar PNGs
            foreach ($sizesPng as $size) {
                $imagick = new \Imagick($inputAbs);
                $imagick->setImageFormat('png');
                $imagick->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1, true);

                $filename = "favicon-{$size}x{$size}.png";
                $output = Storage::path("{$baseFolder}/{$filename}");
                $imagick->writeImage($output);

                if (in_array($size, [16, 32, 48])) {
                    $imagesForIco[] = $output;
                }

                $imagick->destroy();
            }

            // Generar favicon.ico a partir de varias resoluciones
            $ico = new \Imagick();
            foreach ($imagesForIco as $path) {
                $frame = new \Imagick($path);
                $ico->addImage($frame);
                $ico->setImageFormat('ico');
            }
            $icoPath = Storage::path("{$baseFolder}/favicon.ico");
            $ico->writeImages($icoPath, true);
            $ico->destroy();

            // Crear ZIP con todos los archivos
            $zipName = "favicons_{$token}.zip";
            $zipPath = Storage::path("{$baseFolder}/{$zipName}");

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                $files = Storage::files($baseFolder);
                foreach ($files as $f) {
                    $zip->addFile(Storage::path($f), basename($f));
                }
                $zip->close();
            }

            $publicBaseUrl = asset("storage/favicons/{$token}");

            // HTML snippet
            $html = <<<HTML
<!-- Favicons generados con Toolbox Codwelt -->
<link rel="icon" type="image/x-icon" href="{$publicBaseUrl}/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16" href="{$publicBaseUrl}/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="{$publicBaseUrl}/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="48x48" href="{$publicBaseUrl}/favicon-48x48.png">
<link rel="icon" type="image/png" sizes="96x96" href="{$publicBaseUrl}/favicon-96x96.png">
<link rel="apple-touch-icon" sizes="180x180" href="{$publicBaseUrl}/favicon-180x180.png">
<link rel="icon" type="image/png" sizes="192x192" href="{$publicBaseUrl}/favicon-192x192.png">
HTML;

            $zipUrl = "{$publicBaseUrl}/{$zipName}";

            // Limpiar input original
            Storage::delete($inputPath);

            return response()->json([
                'zip_url' => $zipUrl,
                'html_tags' => $html,
                'publicBase' => $publicBaseUrl,
            ]);
        } catch (\Throwable $e) {
            Storage::deleteDirectory($baseFolder);
            Storage::delete($inputPath);

            return response()->json([
                'message' => 'Ocurrió un error al generar los favicons.',
            ], 500);
        }
    }
}
