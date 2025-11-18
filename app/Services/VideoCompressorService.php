<?php

namespace App\Services;

class VideoCompressorService
{
    /**
     * Comprime un video usando FFmpeg.
     *
     * @param string   $inputPath     Ruta absoluta al archivo original
     * @param string   $outputPath    Ruta absoluta donde se guardará el mp4 comprimido
     * @param int      $compression   Porcentaje de compresión 0–100 (0 sin compresión, 80 fuerte)
     * @param int|null $maxWidth      Ancho máximo
     * @param int|null $maxHeight     Alto máximo
     * @return void
     *
     * @throws \RuntimeException
     */
    public function compress(
        string $inputPath,
        string $outputPath,
        int $compression,
        ?int $maxWidth,
        ?int $maxHeight
    ): void {
        // Validar FFmpeg
        $ffmpegPath = trim(shell_exec('which ffmpeg'));
        if (!$ffmpegPath) {
            throw new \RuntimeException('FFmpeg no está disponible en el servidor.');
        }

        // Leer información del video para calcular bitrate
        $probeCmd = sprintf(
            '%s -i %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($inputPath)
        );
        $probeOutput = shell_exec($probeCmd);

        $originalBitrate = $this->extractBitrateFromFfmpegOutput($probeOutput) ?? 2500; // kbps fallback
        // Ajuste simple: reducimos el bitrate según el porcentaje
        $compression = max(10, min(90, $compression)); // limitamos entre 10–90
        $targetBitrate = (int) max(500, $originalBitrate * (100 - $compression) / 100); // kbps

        // Filtro de escala si definimos dimensiones
        $scaleFilter = '';
        if ($maxWidth && $maxHeight) {
            // Mantener proporción dentro del contenedor
            $scaleFilter = sprintf(
                '-vf "scale=\'min(%d,iw)\':-2:force_original_aspect_ratio=decrease"',
                $maxWidth
            );
            // Nota: para controlar alto exacto se podría refinar el filtro.
        }

        $cmd = sprintf(
            '%s -y -i %s %s -c:v libx264 -b:v %dk -preset medium -c:a aac -movflags +faststart %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($inputPath),
            $scaleFilter,
            $targetBitrate,
            escapeshellarg($outputPath)
        );

        $output = shell_exec($cmd);

        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Error al comprimir el video con FFmpeg.');
        }
    }

    /**
     * Extrae el bitrate (kbps) desde la salida de FFmpeg.
     */
    protected function extractBitrateFromFfmpegOutput(?string $output): ?int
    {
        if (!$output) {
            return null;
        }

        // Busca algo como "bitrate: 4567 kb/s"
        if (preg_match('/bitrate:\s+(\d+)\s+kb\/s/i', $output, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
