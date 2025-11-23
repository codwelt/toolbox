<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Web\WhatsappLinkGeneratorController;
use App\Http\Controllers\Api\BackgroundRemoverApiController;
use App\Http\Controllers\Api\VideoCompressorApiController;
use App\Http\Controllers\Web\BackgroundRemoverController;
use App\Http\Controllers\Web\ImageCompressorController;
use App\Http\Controllers\Api\WebpToPngSimpleController;
use App\Http\Controllers\Web\VideoCompressorController;
use App\Http\Controllers\Web\CountryLibraryController;
use App\Http\Controllers\Web\EmojiLibraryController;
use App\Http\Controllers\Web\ImageResizerController;
use App\Http\Controllers\Api\WebpToPngApiController;
use App\Http\Controllers\Api\WatermarkApiController;
use App\Http\Controllers\Api\FaviconsApiController;
use App\Http\Controllers\Web\WebpToPngController;
use App\Http\Controllers\Web\WatermarkController;
use App\Http\Controllers\Web\FaviconsController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\HomeController;

Route::get('/biblioteca-paises-mundo', [CountryLibraryController::class, 'index'])->name('tools.country-library');
Route::get('/biblioteca-emojis', [EmojiLibraryController::class, 'index'])->name('tools.emoji-library');
Route::get('/generador-links-whatsapp', [WhatsappLinkGeneratorController::class, 'index'])->name('tools.whatsapp-link-generator');
Route::get('/comprimir-videos-online', [VideoCompressorController::class, 'index'])->name('tools.video-compressor');
Route::post('/api/tools/video-compressor', [VideoCompressorApiController::class, 'process'])->name('api.tools.video-compressor');
Route::get('/generador-favicons-online', [FaviconsController::class, 'index'])->name('tools.favicons');
Route::post('/api/tools/favicons', [FaviconsApiController::class, 'generate'])->name('api.tools.favicons');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/comprimir-imagenes-online-gratis', [ImageCompressorController::class, 'index'])->name('tools.image-compressor');
Route::get('/quitar-fondo-imagen-gratis', [BackgroundRemoverController::class, 'index'])->name('tools.background-remover');
Route::post('/api/tools/background-remover', [BackgroundRemoverApiController::class, 'process'])->name('api.tools.background-remover');
Route::get('/redimensionar-imagenes-online-gratis', [ImageResizerController::class, 'index'])->name('tools.image-resizer');
Route::get('/convertir-webp-a-png-gratis', [WebpToPngController::class, 'index'])->name('tools.webp-to-png');
Route::post('/api/tools/webp-to-png', [WebpToPngApiController::class, 'process'])->name('api.tools.webp-to-png');
Route::post('/webp-to-png', [WebpToPngSimpleController::class, 'convert'])->name('webp.to.png');
Route::get('/poner-marca-de-agua-imagen-gratis', [WatermarkController::class, 'index'])->name('tools.watermark');
Route::post('/api/tools/watermark', [WatermarkApiController::class, 'process'])->name('api.tools.watermark');