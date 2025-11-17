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

        // Directorio relativo al disco "public"
        $token = uniqid('fav_', true);
        $dir = "favicons/{$token}";

        // Creamos el directorio en storage/app/public/favicons/{token}
        Storage::makeDirectory($dir); // Ojo: sin "public/" aquí, ya estamos en el disco

        // Guardamos el input original en tmp
        $inputPathRelative = $file->store("tmp/favicons_input");
        $inputAbs = Storage::path($inputPathRelative);

        // Tamaños PNG que vamos a generar
        $sizesPng = [
            16,
            32,
            48,
            96,
            180,
            192,
        ];

        try {
            $imagesForIco = [];

            foreach ($sizesPng as $size) {
                $imagick = new \Imagick($inputAbs);
                $imagick->setImageFormat('png');
                $imagick->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1, true);

                $filename = "favicon-{$size}x{$size}.png";
                $outputRelative = "{$dir}/{$filename}";
                $outputAbs = Storage::path($outputRelative);

                $imagick->writeImage($outputAbs);

                if (in_array($size, [16, 32, 48])) {
                    $imagesForIco[] = $outputAbs;
                }

                $imagick->destroy();
            }

            // Generar favicon.ico con varias resoluciones
            $ico = new \Imagick();
            foreach ($imagesForIco as $path) {
                $frame = new \Imagick($path);
                $ico->addImage($frame);
                $ico->setImageFormat('ico');
            }
            $icoRelative = "{$dir}/favicon.ico";
            $icoAbs = Storage::path($icoRelative);
            $ico->writeImages($icoAbs, true);
            $ico->destroy();

            // Crear ZIP (favicons_XXXX.zip)
            $zipName = "favicons_{$token}.zip";
            $zipRelative = "{$dir}/{$zipName}";
            $zipAbs = Storage::path($zipRelative);

            $zip = new ZipArchive();
            if ($zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // Agregamos todos los archivos PNG + ICO (no el ZIP)
                $files = Storage::files($dir);
                foreach ($files as $f) {
                    if (basename($f) === $zipName) {
                        continue; // no agregamos el propio zip dentro de sí mismo
                    }
                    $zip->addFile(Storage::path($f), basename($f));
                }
                $zip->close();
            }

            // URL públicas usando disco "public"
            $publicBaseUrl = Storage::url($dir);          // /storage/favicons/{token}
            $zipUrl = Storage::url($zipRelative);  // /storage/favicons/{token}/favicons_...

            $faviconBaseUrl = rtrim($publicBaseUrl, '/');

            $html = <<<HTML
<!-- Favicons generados con Toolbox Codwelt -->
<link rel="icon" type="image/x-icon" href="{$faviconBaseUrl}/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16" href="{$faviconBaseUrl}/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="{$faviconBaseUrl}/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="48x48" href="{$faviconBaseUrl}/favicon-48x48.png">
<link rel="icon" type="image/png" sizes="96x96" href="{$faviconBaseUrl}/favicon-96x96.png">
<link rel="apple-touch-icon" sizes="180x180" href="{$faviconBaseUrl}/favicon-180x180.png">
<link rel="icon" type="image/png" sizes="192x192" href="{$faviconBaseUrl}/favicon-192x192.png">
HTML;

            // Borramos el input original de tmp
            Storage::delete($inputPathRelative);

            return response()->json([
                'zip_url' => $zipUrl,
                'html_tags' => $html,
                'publicBase' => $faviconBaseUrl,
            ]);
        } catch (\Throwable $e) {
            Storage::deleteDirectory($dir);
            Storage::delete($inputPathRelative);

            return response()->json([
                'message' => 'Ocurrió un error al generar los favicons.',
            ], 500);
        }
    }
}
