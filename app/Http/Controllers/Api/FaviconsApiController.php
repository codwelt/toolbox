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

        // Usamos SIEMPRE el disco "public"
        $disk = Storage::disk('public');

        // Directorio relativo al disco public: storage/app/public/favicons/{token}
        $token = uniqid('fav_', true);
        $dir   = "favicons/{$token}";

        // Creamos el directorio en el disco public
        $disk->makeDirectory($dir);

        // Guardamos la imagen original también en el disco public (puede borrarse luego)
        $inputRelative = $file->store("favicons_input/{$token}", 'public');
        $inputAbs      = $disk->path($inputRelative);

        // Tamaños PNG a generar
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

                $filename       = "favicon-{$size}x{$size}.png";
                $outputRelative = "{$dir}/{$filename}";
                $outputAbs      = $disk->path($outputRelative);

                $imagick->writeImage($outputAbs);

                if (in_array($size, [16, 32, 48])) {
                    $imagesForIco[] = $outputAbs;
                }

                $imagick->destroy();
            }

            // Generar favicon.ico
            $ico = new \Imagick();
            foreach ($imagesForIco as $path) {
                $frame = new \Imagick($path);
                $ico->addImage($frame);
                $ico->setImageFormat('ico');
            }

            $icoRelative = "{$dir}/favicon.ico";
            $icoAbs      = $disk->path($icoRelative);
            $ico->writeImages($icoAbs, true);
            $ico->destroy();

            // Crear ZIP dentro de favicons/{token}
            $zipName     = "favicons_{$token}.zip";
            $zipRelative = "{$dir}/{$zipName}";
            $zipAbs      = $disk->path($zipRelative);

            $zip = new ZipArchive();
            if ($zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $files = $disk->files($dir);
                foreach ($files as $f) {
                    if (basename($f) === $zipName) {
                        continue; // no metemos el propio ZIP dentro de sí mismo
                    }
                    $zip->addFile($disk->path($f), basename($f));
                }
                $zip->close();
            }

            // URLs públicas usando disco public (→ /storage/favicons/...)
            $faviconBaseUrl = rtrim($disk->url($dir), '/');
            $zipUrl         = $disk->url($zipRelative);

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

            // Limpieza del input original
            $disk->delete($inputRelative);

            return response()->json([
                'zip_url'    => $zipUrl,
                'html_tags'  => $html,
                'publicBase' => $faviconBaseUrl,
            ]);
        } catch (\Throwable $e) {
            // Limpieza si algo falla
            $disk->deleteDirectory($dir);
            $disk->delete($inputRelative);

            return response()->json([
                'message' => 'Ocurrió un error al generar los favicons.',
            ], 500);
        }
    }
}
