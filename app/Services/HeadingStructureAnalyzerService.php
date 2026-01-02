<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class HeadingStructureAnalyzerService
{
    public function analyze(string $url): array
    {
        $normalized = $this->normalizeUrl($url);
        if (!$normalized) {
            throw new \InvalidArgumentException('URL inválida.');
        }

        [$html, $usedFallback] = $this->fetchHtml($normalized);
        $doc = $this->loadDocument($html);

        $headings = $this->extractHeadings($doc);
        $analysis = $this->analyzeHeadings($headings);

        return [
            'url' => $normalized,
            'domain' => parse_url($normalized, PHP_URL_HOST) ?? '',
            'used_fallback' => $usedFallback,
            'headings' => $headings,
            'counts' => $analysis['counts'],
            'checks' => $analysis['checks'],
            'recommendations' => $analysis['recommendations'],
            'score' => $analysis['score'],
            'outline' => $analysis['outline'],
        ];
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if (!$url) {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ?: null;
    }

    private function fetchHtml(string $url): array
    {
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml',
            'User-Agent' => 'Toolsbox-SEO-Headings/1.0',
        ];

        try {
            $response = Http::withHeaders($headers)->timeout(10)->get($url);
            if ($response->successful()) {
                return [$response->body(), false];
            }
        } catch (\Throwable $e) {
            // ignore and try fallback
        }

        $fallbackUrl = 'https://r.jina.ai/' . $url;
        $fallbackText = Http::withHeaders($headers)->timeout(10)->get($fallbackUrl)->body();
        return [$this->buildHtmlFromFallback($fallbackText), true];
    }

    private function buildHtmlFromFallback(string $fallbackText): string
    {
        $escaped = htmlspecialchars($fallbackText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Fallback</title>
</head>
<body>
<pre>{$escaped}</pre>
</body>
</html>
HTML;
    }

    private function loadDocument(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        libxml_clear_errors();
        return $doc;
    }

    private function extractHeadings(DOMDocument $doc): array
    {
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]');

        $headings = [];
        $position = 0;
        foreach ($nodes as $node) {
            $position++;
            $tag = strtolower($node->nodeName);
            $level = (int) str_replace('h', '', $tag);
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));

            $headings[] = [
                'level' => $level,
                'tag' => strtoupper($tag),
                'text' => $text,
                'position' => $position,
            ];
        }

        return $headings;
    }

    private function analyzeHeadings(array $headings): array
    {
        $checks = [];
        $add = function ($label, $ok, $detail, $weight = 1) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => (bool) $ok, 'detail' => $detail, 'weight' => $weight];
        };

        $counts = [];
        for ($i = 1; $i <= 6; $i++) {
            $counts['h' . $i] = 0;
        }
        foreach ($headings as $heading) {
            $counts['h' . $heading['level']]++;
        }

        $outline = array_map(function ($heading) {
            return [
                'level' => $heading['level'],
                'label' => 'H' . $heading['level'],
                'text' => $heading['text'],
                'position' => $heading['position'],
            ];
        }, $headings);

        $hasHeadings = !empty($headings);
        $add(
            'Encabezados detectados',
            $hasHeadings,
            $hasHeadings ? count($headings) . ' encontrados' : 'No se detectaron H1-H6',
            1.5
        );

        $h1s = array_values(array_filter($headings, fn($h) => $h['level'] === 1));
        $h2s = array_values(array_filter($headings, fn($h) => $h['level'] === 2));
        $emptyHeadings = array_values(array_filter($headings, fn($h) => $h['text'] === ''));

        $h1Count = count($h1s);
        $h1TextList = array_values(array_filter(array_map(fn($h) => $h['text'], $h1s)));

        $add(
            'H1 único',
            $h1Count === 1,
            $h1Count === 1 ? 'H1: ' . ($h1TextList[0] ?? '') : ($h1Count === 0 ? 'Sin H1' : 'Se detectaron ' . $h1Count . ' H1'),
            2
        );

        $firstHeading = $headings[0]['level'] ?? null;
        $add(
            'El primer heading es H1',
            $firstHeading === 1,
            $firstHeading ? 'Primer heading: H' . $firstHeading : 'Sin headings',
            1.5
        );

        $skips = [];
        $prevLevel = null;
        foreach ($headings as $heading) {
            $level = $heading['level'];
            if ($prevLevel !== null && ($level - $prevLevel) > 1) {
                $skips[] = 'H' . $prevLevel . ' → H' . $level;
            }
            $prevLevel = $level;
        }
        $add(
            'Sin saltos de nivel',
            empty($skips),
            empty($skips) ? 'Jerarquía continua' : implode(', ', $skips),
            1.5
        );

        $add(
            'H2 presentes',
            count($h2s) >= 1,
            count($h2s) >= 1 ? 'H2: ' . count($h2s) : 'No hay H2',
            1
        );

        $add(
            'Encabezados con texto',
            count($emptyHeadings) === 0,
            count($emptyHeadings) === 0 ? 'Todos con texto' : count($emptyHeadings) . ' sin texto',
            1
        );

        $h1Length = $h1TextList ? $this->stringLength($h1TextList[0]) : 0;
        $add(
            'Longitud H1 recomendada (20-70 caracteres)',
            $h1Length >= 20 && $h1Length <= 70,
            $h1Length ? $h1Length . ' caracteres' : 'Sin H1',
            1
        );

        $score = $this->calculateScore($checks);
        $recommendations = $this->buildRecommendations($checks);

        return [
            'counts' => $counts,
            'checks' => $checks,
            'recommendations' => $recommendations,
            'score' => $score,
            'outline' => $outline,
        ];
    }

    private function buildRecommendations(array $checks): array
    {
        $map = [
            'Encabezados detectados' => 'Incluya encabezados H1-H6 para organizar el contenido y mejorar la legibilidad.',
            'H1 único' => 'Mantenga un único H1 que represente el tema principal de la página.',
            'El primer heading es H1' => 'Inicie la jerarquía con un H1 y luego desarrolle las secciones con H2 y niveles inferiores.',
            'Sin saltos de nivel' => 'Evite saltos de jerarquía (por ejemplo, de H2 a H4). Use niveles consecutivos.',
            'H2 presentes' => 'Agregue H2 para dividir el contenido en secciones principales y facilitar la lectura.',
            'Encabezados con texto' => 'Complete o elimine encabezados vacíos para evitar señales de contenido incompleto.',
            'Longitud H1 recomendada (20-70 caracteres)' => 'Ajuste el H1 a un rango de 20 a 70 caracteres para mayor claridad.',
        ];

        $recommendations = [];
        foreach ($checks as $check) {
            if (!$check['ok'] && isset($map[$check['label']])) {
                $recommendations[] = $map[$check['label']];
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'La estructura de títulos cumple con las buenas prácticas SEO más comunes.';
        }

        return array_values(array_unique($recommendations));
    }

    private function calculateScore(array $checks): int
    {
        $total = array_reduce($checks, fn($acc, $c) => $acc + ($c['weight'] ?? 1), 0);
        $ok = array_reduce($checks, fn($acc, $c) => $acc + ($c['ok'] ? ($c['weight'] ?? 1) : 0), 0);
        return (int) round(($ok / max($total, 1)) * 100);
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
