<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiWebsiteDetectorService
{
    private array $aiBuilders = [
        ['name' => 'Framer (AI builder)', 'markers' => ['framer.com', 'framer.site', 'data-framer', 'framer-analytics']],
        ['name' => 'Wix ADI', 'markers' => ['wix-adi', 'wix.com', 'x-wix']],
        ['name' => 'Durable.co', 'markers' => ['durable.co', 'durable.ai']],
        ['name' => '10Web', 'markers' => ['10web', 'ai-site', '10web.io']],
        ['name' => 'Bookmark AiDA', 'markers' => ['aida', 'bookmark.com', 'bookmarkcdn']],
        ['name' => 'Hostinger Website Builder', 'markers' => ['hostinger', 'hbuilder', 'zyro.com']],
        ['name' => 'GoDaddy Airo', 'markers' => ['godaddy', 'airo']],
        ['name' => 'Webflow AI', 'markers' => ['webflow.io', 'webflow.com', 'w-nav', 'w-slider']],
    ];

    public function analyze(string $url): array
    {
        $normalized = $this->normalizeUrl($url);
        if (!$normalized) {
            throw new \InvalidArgumentException('URL inválida.');
        }

        [$html, $usedFallback] = $this->fetchHtml($normalized);
        $analysis = $this->analyzeHtml($html, $normalized);

        $analysis['used_fallback'] = $usedFallback;

        return $analysis;
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
            'User-Agent' => 'Toolsbox-AI-Detector/1.0',
        ];

        try {
            $resp = Http::withHeaders($headers)->timeout(10)->get($url);
            if ($resp->successful()) {
                return [$resp->body(), false];
            }
        } catch (\Throwable $e) {
            // fall through to fallback
        }

        $fallbackUrl = 'https://r.jina.ai/' . $url;
        $fallbackBody = Http::withHeaders($headers)->timeout(10)->get($fallbackUrl)->body();
        return [$this->buildHtmlFromFallback($fallbackBody, $url), true];
    }

    private function analyzeHtml(string $html, string $targetUrl): array
    {
        $doc = $this->loadDocument($html);
        $xpath = new DOMXPath($doc);
        $htmlLower = strtolower($html);
        $text = $this->extractVisibleText($doc);
        $metrics = $this->computeTextMetrics($text);
        $builderMatches = $this->detectAiBuilders($htmlLower);
        $metaSignals = $this->detectMetaSignals($xpath, $htmlLower);
        $aiScore = $this->estimateAiScore($builderMatches, $metaSignals, $metrics);

        return [
            'url' => $targetUrl,
            'domain' => parse_url($targetUrl, PHP_URL_HOST) ?? '',
            'ai_score' => $aiScore,
            'builder_matches' => $builderMatches,
            'meta_signals' => $metaSignals,
            'metrics' => $metrics,
            'text_preview' => Str::limit($text, 800, ''),
        ];
    }

    private function loadDocument(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        libxml_clear_errors();
        return $doc;
    }

    private function extractVisibleText(DOMDocument $doc): string
    {
        $xpath = new DOMXPath($doc);
        $node = $xpath->query('//main')->item(0) ?: $doc->getElementsByTagName('body')->item(0);
        $text = $node ? $node->textContent : $doc->textContent;
        return trim(preg_replace('/\s+/', ' ', $text ?? ''));
    }

    private function computeTextMetrics(string $text): array
    {
        if (!$text) {
            return [
                'wordCount' => 0,
                'sentenceCount' => 0,
                'avgSentenceLength' => 0,
                'typeTokenRatio' => 0,
                'repetitionRatio' => 0,
            ];
        }

        $words = preg_split('/\s+/', $text);
        $sentences = preg_split('/[.!?¡¿;:]+/', $text);
        $words = array_values(array_filter($words));
        $sentences = array_values(array_filter(array_map('trim', $sentences)));

        $wordCount = count($words);
        $sentenceCount = $sentences ? count($sentences) : 1;
        $avgSentenceLength = $wordCount / $sentenceCount;

        $freq = [];
        foreach ($words as $w) {
            $key = Str::lower($w);
            $freq[$key] = ($freq[$key] ?? 0) + 1;
        }
        $typeTokenRatio = count($freq) ? (count($freq) / max($wordCount, 1)) * 100 : 0;
        $topCounts = collect($freq)->sortDesc()->take(8)->sum();
        $repetitionRatio = $wordCount ? ($topCounts / $wordCount) * 100 : 0;

        return [
            'wordCount' => $wordCount,
            'sentenceCount' => $sentenceCount,
            'avgSentenceLength' => round($avgSentenceLength, 1),
            'typeTokenRatio' => round($typeTokenRatio),
            'repetitionRatio' => round($repetitionRatio),
        ];
    }

    private function detectAiBuilders(string $htmlLower): array
    {
        $matches = [];
        foreach ($this->aiBuilders as $builder) {
            $found = array_values(array_filter($builder['markers'], fn($m) => str_contains($htmlLower, $m)));
            if ($found) {
                $matches[] = ['name' => $builder['name'], 'markers' => $found];
            }
        }
        return $matches;
    }

    private function detectMetaSignals(DOMXPath $xpath, string $htmlLower): array
    {
        $signals = [];
        $generator = $this->getMetaByNameOrProperty($xpath, 'generator');
        if ($generator) {
            $signals[] = ['label' => 'Meta generator', 'value' => $generator];
        }

        $metaNodes = $xpath->query('//meta');
        $added = 0;
        foreach ($metaNodes as $meta) {
            $name = strtolower($meta->getAttribute('name') ?? $meta->getAttribute('property'));
            $content = strtolower($meta->getAttribute('content'));
            if (($name && str_contains($name, 'ai')) || ($content && str_contains($content, 'ai'))) {
                if (str_contains($name, 'build') || str_contains($content, 'generated')) {
                    $signals[] = ['label' => "Meta {$name}", 'value' => $meta->getAttribute('content')];
                    $added++;
                }
            }
            if ($added >= 3) {
                break;
            }
        }

        if (str_contains($htmlLower, 'ai-generated') || str_contains($htmlLower, 'data-ai')) {
            $signals[] = ['label' => 'Marcas IA', 'value' => 'Se encontraron atributos o etiquetas con "ai".'];
        }

        return $signals;
    }

    private function estimateAiScore(array $builderMatches, array $metaSignals, array $metrics): int
    {
        $score = 30;
        if (!empty($builderMatches)) {
            $score += 30;
        }
        if (!empty($metaSignals)) {
            $score += 10;
        }
        if ($metrics['typeTokenRatio'] < 35) {
            $score += 10;
        }
        if ($metrics['repetitionRatio'] > 18) {
            $score += 8;
        }
        if ($metrics['avgSentenceLength'] >= 10 && $metrics['avgSentenceLength'] <= 18) {
            $score += 5;
        }
        if ($metrics['wordCount'] < 80) {
            $score = max($score - 8, 0);
        }

        return (int) min(100, max(0, round($score)));
    }

    private function getMetaByNameOrProperty(DOMXPath $xpath, string $name): ?string
    {
        $query = "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$name}' or translate(@property, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$name}']";
        $node = $xpath->query($query)->item(0);
        if ($node) {
            $content = $node->getAttribute('content');
            return $content ? trim($content) : null;
        }
        return null;
    }

    private function buildHtmlFromFallback(string $rawText, string $targetUrl): string
    {
        $lines = collect(preg_split('/\r?\n/', $rawText ?? '') ?: [])->map(fn($l) => trim($l))->filter()->values();
        $title = $lines->first() ?: $targetUrl;
        $description = $lines->filter(fn($l) => strlen($l) > 20)->first() ?: $title;
        $bodyParts = $lines->map(function ($line) {
            if (Str::startsWith($line, '# ')) {
                return '<h1>' . e(Str::after($line, '# ')) . '</h1>';
            }
            if (Str::startsWith($line, '## ')) {
                return '<h2>' . e(Str::after($line, '## ')) . '</h2>';
            }
            if (Str::startsWith($line, '### ')) {
                return '<h3>' . e(Str::after($line, '### ')) . '</h3>';
            }
            return '<p>' . e($line) . '</p>';
        })->implode("\n");

        return <<<HTML
        <html>
            <head>
                <title>{$title}</title>
                <meta name="description" content="{$description}">
                <link rel="canonical" href="{$targetUrl}">
            </head>
            <body>
                {$bodyParts}
            </body>
        </html>
        HTML;
    }
}
