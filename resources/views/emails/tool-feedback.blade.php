<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mensaje desde Toolbox</title>
</head>
<body style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1e1e1e;">
    <p style="margin-bottom: 0.25rem;">Herramienta: <strong>{{ $toolName }}</strong></p>
    <p style="margin-top: 0.25rem; margin-bottom: 1rem;">URL: <a href="{{ $toolUrl }}">{{ $toolUrl }}</a></p>

    <p style="margin-bottom: 0.1rem;"><strong>Mensaje:</strong></p>
    <p style="margin-top: 0; margin-bottom: 1rem;">{!! nl2br(e($messageText)) !!}</p>

    @if($contactEmail)
        <p style="margin-bottom: 1rem;"><strong>Correo de contacto:</strong> {{ $contactEmail }}</p>
    @endif

    <p style="margin-bottom: 0.25rem; font-size: 0.85rem; color: #555;">IP: {{ $ipAddress ?? 'No disponible' }}</p>
    <p style="margin-top: 0; margin-bottom: 1.5rem; font-size: 0.85rem; color: #555;">User Agent: {{ $userAgent ?? 'No disponible' }}</p>

    <p style="font-size: 0.75rem; color: #555;">Enviado el {{ now()->format('d/m/Y H:i') }} | Toolbox Codwelt</p>
</body>
</html>
