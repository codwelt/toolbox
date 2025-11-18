<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VideoCompressorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VideoCompressorApiController extends Controller
{
    public function process(Request $request, VideoCompressorService $service)
    {
        $request->validate([
            'video'        => 'required|file|mimetypes:video/mp4,video/quicktime|max:204800', // ~200MB
            'compression'  => 'required|integer|min:10|max:90',  // 10–90 %
            'max_width'    => 'nullable|integer|min:320|max:3840',
            'max_height'   => 'nullable|integer|min:240|max:2160',
        ]);

        $file        = $request->file('video');
        $compression = (int) $request->input('compression');
        $maxWidth    = $request->input('max_width');
        $maxHeight   = $request->input('max_height');

        // Guardamos original temporalmente
        $originalRelative = $file->store('tmp/video-compressor/original');
        $inputPath        = Storage::path($originalRelative);

        // Ruta de salida temporal
        $outputRelative = 'tmp/video-compressor/output_' . uniqid() . '.mp4';
        $outputPath     = Storage::path($outputRelative);

        try {
            $service->compress(
                $inputPath,
                $outputPath,
                $compression,
                $maxWidth ? (int) $maxWidth : null,
                $maxHeight ? (int) $maxHeight : null
            );

            $videoContent = Storage::get($outputRelative);

            return response($videoContent, 200)
                ->header('Content-Type', 'video/mp4');
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error al comprimir el video.',
            ], 500);
        } finally {
            // Limpieza
            Storage::delete($originalRelative);
            Storage::delete($outputRelative);
        }
    }
}
