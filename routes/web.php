<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\ImageCompressorController;
use App\Http\Controllers\Web\BackgroundRemoverController;
use App\Http\Controllers\Api\BackgroundRemoverApiController;
use App\Http\Controllers\Api\WebpToPngSimpleController;
use App\Http\Controllers\Web\ImageResizerController;
use App\Http\Controllers\Api\WebpToPngApiController;
use App\Http\Controllers\Api\WatermarkApiController;
use App\Http\Controllers\Web\WebpToPngController;
use App\Http\Controllers\Web\WatermarkController;
use App\Http\Controllers\Web\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/comprimir-imagenes-online-gratis', [ImageCompressorController::class, 'index'])
    ->name('tools.image-compressor');

Route::get('/quitar-fondo-imagen-gratis', [BackgroundRemoverController::class, 'index'])
    ->name('tools.background-remover');

Route::post('/api/tools/background-remover', [BackgroundRemoverApiController::class, 'process'])
    ->name('api.tools.background-remover');

Route::get('/redimensionar-imagenes-online', [ImageResizerController::class, 'index'])
    ->name('tools.image-resizer');

Route::get('/convertir-webp-a-png-gratis', [WebpToPngController::class, 'index'])
    ->name('tools.webp-to-png');

Route::post('/api/tools/webp-to-png', [WebpToPngApiController::class, 'process'])
    ->name('api.tools.webp-to-png');

Route::post('/webp-to-png', [WebpToPngSimpleController::class, 'convert'])
    ->name('webp.to.png');

Route::get('/poner-marca-de-agua-imagen', [WatermarkController::class, 'index'])
    ->name('tools.watermark');

Route::post('/api/tools/watermark', [WatermarkApiController::class, 'process'])
    ->name('api.tools.watermark');
