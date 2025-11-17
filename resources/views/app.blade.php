<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicons generados con Toolbox Codwelt -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png">
    <title inertia>{{ config('app.name', 'ToolsBox') }}</title>
    {{-- Google Tag Manager --}}
    @if (app()->environment('production') && config('services.gtm.id'))
        <!-- Google Tag Manager -->
        <script>
            (function (w, d, s, l, i) {
                w[l] = w[l] || []; w[l].push({
                    'gtm.start':
                        new Date().getTime(), event: 'gtm.js'
                }); var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                        'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '{{ config('services.gtm.id') }}');
        </script>
        <!-- End Google Tag Manager -->
    @endif
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead

</head>

<body class="font-sans antialiased">
    {{-- Google Tag Manager (noscript) --}}
    @if (app()->environment('production') && config('services.gtm.id'))
        <!-- Google Tag Manager (noscript) -->
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm.id') }}" height="0" width="0"
                style="display:none;visibility:hidden"></iframe>
        </noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif

    @inertia
</body>

</html>