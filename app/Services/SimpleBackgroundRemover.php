<?php

namespace App\Services;

class SimpleBackgroundRemover
{
    /**
     * Elimina el fondo a partir del color de una esquina
     * y redimensiona según el modo indicado.
     *
     * @param string $inputPath   Ruta absoluta al archivo original
     * @param string $outputPath  Ruta absoluta donde se guardará el PNG final
     * @param string $sizeMode    original|preset|custom
     * @param string|null $preset small|medium|large
     * @param int|null $customWidth
     * @param int|null $customHeight
     * @return void
     * @throws \RuntimeException
     */
    public function process(
        string $inputPath,
        string $outputPath,
        string $sizeMode = 'original',
        ?string $preset = null,
        ?int $customWidth = null,
        ?int $customHeight = null
    ): void {
        if (!extension_loaded('imagick')) {
            throw new \RuntimeException('La extensión Imagick no está habilitada en el servidor.');
        }

        $imagick = new \Imagick($inputPath);

        // Aseguramos canal alfa
        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);

        // Tomamos el color de la esquina superior izquierda como color de fondo
        $bgColor = $imagick->getImagePixelColor(1, 1);

        // Tolerancia de color (fuzz). Ajustable según resultados.
        $fuzz = 0.10 * \Imagick::getQuantum(); // ~10%

        // Hacemos transparente todo lo cercano a ese color de fondo
        $imagick->transparentPaintImage(
            $bgColor,
            0.0,     // opacidad objetivo (0 = transparente)
            $fuzz,   // tolerancia
            false    // no invertir
        );

        // Recortamos bordes transparentes para ajustar el sujeto
        $imagick->trimImage(0);

        // Redimensionar según size_mode
        $this->resize($imagick, $sizeMode, $preset, $customWidth, $customHeight);

        // Guardamos en PNG con transparencia
        $imagick->setImageFormat('png');
        $imagick->writeImage($outputPath);

        $imagick->destroy();
    }

    protected function resize(
        \Imagick $imagick,
        string $sizeMode,
        ?string $preset,
        ?int $customWidth,
        ?int $customHeight
    ): void {
        if ($sizeMode === 'original') {
            return; // no hacemos resize
        }

        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($sizeMode === 'preset') {
            $maxLongSide = $this->getPresetMaxLongSide($preset);

            if (!$maxLongSide) {
                return;
            }

            $longSide = max($width, $height);
            if ($longSide <= $maxLongSide) {
                return; // ya es más pequeña o igual
            }

            $ratio = $maxLongSide / $longSide;
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);

            $imagick->resizeImage($newWidth, $newHeight, \Imagick::FILTER_LANCZOS, 1);
            return;
        }

        if ($sizeMode === 'custom') {
            // Si solo viene ancho o solo alto, mantenemos proporción
            if ($customWidth && !$customHeight) {
                $ratio = $customWidth / $width;
                $customHeight = (int) round($height * $ratio);
            } elseif ($customHeight && !$customWidth) {
                $ratio = $customHeight / $height;
                $customWidth = (int) round($width * $ratio);
            }

            if ($customWidth && $customHeight) {
                $imagick->resizeImage($customWidth, $customHeight, \Imagick::FILTER_LANCZOS, 1);
            }
        }
    }

    protected function getPresetMaxLongSide(?string $preset): ?int
    {
        return match ($preset) {
            'small'  => 800,
            'medium' => 1200,
            'large'  => 1920,
            default  => null,
        };
    }
}
