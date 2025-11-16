<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\ImageCompressorController;
use App\Http\Controllers\Web\BackgroundRemoverController;
use App\Http\Controllers\Api\BackgroundRemoverApiController;



Route::get('/comprimir-imagenes-online-gratis', [ImageCompressorController::class, 'index'])
    ->name('tools.image-compressor');

Route::get('/quitar-fondo-imagen-gratis', [BackgroundRemoverController::class, 'index'])
    ->name('tools.background-remover');

Route::post('/api/tools/background-remover', [BackgroundRemoverApiController::class, 'process'])
    ->name('api.tools.background-remover');
