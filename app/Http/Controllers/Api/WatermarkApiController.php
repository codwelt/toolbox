<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WatermarkApiController extends Controller
{
    public function process(Request $request)
    {
        $request->validate([
            'base_image' => 'required|image|max:8192',
            'watermark_image' => 'required|image|max:4096',
            'position' => 'required|in:top-left,top-right,bottom-left,bottom-right,center',
            'opacity' => 'required|integer|min:10|max:100',   // 10 a 100%
            'scale_percent' => 'nullable|integer|min:10|max:200',   // tamaño marca 10% a 200% ancho base
            'output_format' => 'nullable|in:png,jpeg',
        ]);

        if (!extension_loaded('imagick')) {
            return response()->json([
                'message' => 'La extensión Imagick no está habilitada en el servidor.',
            ], 500);
        }

        $baseFile = $request->file('base_image');
        $watermarkFile = $request->file('watermark_image');

        $baseRelative = $baseFile->store('tmp/watermark/base');
        $watermarkRelative = $watermarkFile->store('tmp/watermark/logo');

        $basePath = Storage::path($baseRelative);
        $watermarkPath = Storage::path($watermarkRelative);

        $position = $request->input('position');
        $opacity = (int) $request->input('opacity', 60);
        $scalePercent = $request->input('scale_percent');
        $outputFormat = $request->input('output_format', 'png'); // default PNG

        try {
            $base = new \Imagick($basePath);
            $watermark = new \Imagick($watermarkPath);

            $base->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
            $watermark->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);

            $baseWidth = $base->getImageWidth();
            $baseHeight = $base->getImageHeight();

            // Escalar watermark en función del ancho de la imagen base
            if ($scalePercent) {
                $scale = $scalePercent / 100;
                $targetWidth = (int) round($baseWidth * $scale);
                $ratio = $targetWidth / $watermark->getImageWidth();
                $targetHeight = (int) round($watermark->getImageHeight() * $ratio);

                $watermark->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1);
            }

            // Aplicar opacidad (0-1)
            $watermarkAlpha = max(0.0, min(1.0, $opacity / 100));
            $watermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $watermarkAlpha, \Imagick::CHANNEL_ALPHA);

            $wmWidth = $watermark->getImageWidth();
            $wmHeight = $watermark->getImageHeight();

            // Calcular posición
            $x = 0;
            $y = 0;
            $margin = 10;

            switch ($position) {
                case 'top-left':
                    $x = $margin;
                    $y = $margin;
                    break;
                case 'top-right':
                    $x = $baseWidth - $wmWidth - $margin;
                    $y = $margin;
                    break;
                case 'bottom-left':
                    $x = $margin;
                    $y = $baseHeight - $wmHeight - $margin;
                    break;
                case 'bottom-right':
                    $x = $baseWidth - $wmWidth - $margin;
                    $y = $baseHeight - $wmHeight - $margin;
                    break;
                case 'center':
                default:
                    $x = (int) (($baseWidth - $wmWidth) / 2);
                    $y = (int) (($baseHeight - $wmHeight) / 2);
                    break;
            }

            // Componer watermark sobre base
            $base->compositeImage($watermark, \Imagick::COMPOSITE_OVER, $x, $y);

            // Formato de salida
            $mime = 'image/png';
            if ($outputFormat === 'jpeg') {
                $base->setImageFormat('jpeg');
                $base->setImageCompressionQuality(90);
                $mime = 'image/jpeg';
            } else {
                $base->setImageFormat('png');
            }

            $blob = $base->getImagesBlob();

            $base->clear();
            $base->destroy();
            $watermark->clear();
            $watermark->destroy();

            return response($blob, 200)->header('Content-Type', $mime);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrió un error al aplicar la marca de agua.',
            ], 500);
        } finally {
            Storage::delete($baseRelative);
            Storage::delete($watermarkRelative);
        }
    }
}
