<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ToolFeedbackMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ColorFeedbackController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:5000',
            'email' => 'nullable|email|max:255',
        ]);

        $tool = config('tools.categories.colors.items.color_palette', []);
        $toolName = $tool['title'] ?? 'Paleta de colores';
        $toolUrl = $tool['canonical'] ?? url('/paleta-de-colores-online');

        $recipient = config('mail.support', config('mail.from.address'));
        if (!$recipient) {
            return response()->json(['message' => 'No hay un correo de soporte configurado.'], 500);
        }

        try {
            Mail::to($recipient)->send(new ToolFeedbackMail(
                $data['message'],
                $data['email'] ?? null,
                $toolName,
                $toolUrl,
                $request->header('User-Agent'),
                $request->ip()
            ));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No pudimos enviar tu mensaje. Intenta nuevamente más tarde.',
            ], 500);
        }

        return response()->json(['message' => 'Tu mensaje fue enviado correctamente.']);
    }
}
