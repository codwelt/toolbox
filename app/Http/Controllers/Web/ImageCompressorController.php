<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ImageCompressorController extends Controller
{
    public function index()
    {
        return Inertia::render('Compressor/Index', [
            'title' => 'Compresor de Imágenes Online',
        ]);
    }
}
