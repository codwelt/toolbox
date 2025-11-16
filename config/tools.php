<?php

return [
    'image_compressor' => [
        'route' => 'tools.image-compressor',
        'slug' => 'comprimir-imagenes-online',
        'path' => '/comprimir-imagenes-online',
        'title' => 'Comprimir imágenes online gratis sin perder calidad | Toolbox Codwelt',
        'description' => 'Reduce el peso de tus imágenes JPG, PNG y WebP sin perder calidad visible. Comprime varias imágenes online gratis con Toolbox de Codwelt.',
        'h1' => 'Comprimir imágenes online gratis',
        'keywords' => [
            'comprimir imágenes online',
            'reducir peso de imágenes',
            'comprimir fotos sin perder calidad',
            'optimizar imágenes para web',
        ],
        'canonical' => 'https://toolbox.codwelt.com/comprimir-imagenes-online-gratis',
        'faq' => [
            [
                'question' => '¿Cómo comprimir imágenes sin perder calidad visible?',
                'answer' => 'Solo sube tus imágenes, ajusta la calidad y el tamaño máximo y descarga la versión optimizada. El algoritmo mantiene el equilibrio entre peso y nitidez.',
            ],
            [
                'question' => '¿Mis imágenes se guardan en el servidor?',
                'answer' => 'No. El procesamiento se realiza directamente en tu navegador, por lo que tus archivos no se almacenan en nuestros servidores.',
            ],
        ],
    ],
    'background_remover' => [
        'route' => 'tools.background-remover',
        'slug' => 'quitar-fondo-imagen',
        'path' => '/quitar-fondo-imagen',
        'title' => 'Quitar fondo de imágenes online gratis | Toolbox Codwelt',
        'description' => 'Elimina el fondo de tus imágenes automáticamente con inteligencia artificial. Descarga tu imagen en PNG transparente en distintos tamaños, lista para usar en tu web o redes sociales.',
        'h1' => 'Quitar fondo de imágenes online',
        'keywords' => [
            'quitar fondo imagen',
            'borrar fondo foto online',
            'eliminar fondo de imágenes',
            'png transparente online',
            'remove background',
        ],
        'canonical' => 'https://toolbox.codwelt.com/quitar-fondo-imagen',
        'faq' => [
            [
                'question' => '¿Cómo funciona la herramienta para quitar el fondo de una imagen?',
                'answer' => 'Subes tu imagen, la herramienta procesa el archivo y genera una versión en PNG con el fondo transparente lista para descargar.',
            ],
            [
                'question' => '¿Puedo elegir el tamaño de la imagen al descargar?',
                'answer' => 'Sí. Puedes descargar la imagen en tamaño original o elegir entre diferentes tamaños predefinidos, e incluso definir un ancho y alto personalizado.',
            ],
            [
                'question' => '¿Mis imágenes se almacenan en el servidor?',
                'answer' => 'Puedes configurar el servicio para procesar las imágenes sin guardarlas permanentemente. La implementación por defecto está pensada para procesar y eliminar los archivos después de un tiempo corto.',
            ],
        ],
    ],
];
