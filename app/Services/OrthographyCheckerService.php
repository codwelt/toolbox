<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use function e;

class OrthographyCheckerService
{
    private const MAX_TEXT_LENGTH = 8000;

    public function analyze(?string $text, ?string $url): array
    {
        if (empty($text) && empty($url)) {
            throw new \InvalidArgumentException('Proporciona texto o una URL para analizar.');
        }

        $source = 'text';
        $normalizedUrl = null;
        $usedFallback = false;

        $textValue = trim((string) $text);

        if (!empty($url)) {
            $normalizedUrl = $this->normalizeUrl($url);
            if (!$normalizedUrl) {
                throw new \InvalidArgumentException('URL inválida.');
            }

            $source = 'url';
            $fetchResult = $this->fetchHtml($normalizedUrl);
            $textValue = $this->extractTextFromHtml($fetchResult['html']);
            $usedFallback = $fetchResult['used_fallback'];
        }

        if ($textValue === '') {
            throw new \InvalidArgumentException('No hay texto para analizar.');
        }

        $truncated = $this->truncateText($textValue);
        $analysis = $this->callLanguageTool($truncated);
        $matches = $this->formatMatches($analysis['matches'] ?? []);

        return [
            'text' => $truncated,
            'original_text' => $textValue,
            'language' => $analysis['language'] ?? 'es',
            'matches' => $matches,
            'matches_count' => count($matches),
            'source' => $source,
            'url' => $normalizedUrl,
            'used_fallback' => $usedFallback,
            'max_length' => self::MAX_TEXT_LENGTH,
            'analyzed_length' => mb_strlen($truncated),
            'input_length' => mb_strlen($textValue),
            'truncated' => mb_strlen($truncated) < mb_strlen($textValue),
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
            'User-Agent' => 'Toolbox-OrthographyChecker/1.0',
        ];

        try {
            $response = Http::withHeaders($headers)->timeout(12)->get($url);
            if ($response->successful()) {
                return ['html' => $response->body(), 'used_fallback' => false];
            }
        } catch (\Throwable $e) {
            // Continue to fallback
        }

        try {
            $fallbackText = Http::withHeaders($headers)->timeout(12)->get('https://r.jina.ai/' . $url)->body();
        } catch (\Throwable $e) {
            throw new \RuntimeException('No pudimos cargar la página.');
        }

        return ['html' => $this->buildHtmlFromFallback($fallbackText, $url), 'used_fallback' => true];
    }

    private function extractTextFromHtml(string $html): string
    {
        $doc = $this->loadDocument($html);
        $xpath = new DOMXPath($doc);
        foreach (['script', 'style', 'noscript', 'template'] as $tag) {
            foreach ($xpath->query("//{$tag}") as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $text = $body ? $body->textContent : $doc->textContent;

        return $this->normalizeWhitespace((string) $text);
    }

    private function loadDocument(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html);
        libxml_clear_errors();

        return $doc;
    }

    private function truncateText(string $text): string
    {
        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }

    private function normalizeWhitespace(string $text): string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($text));
        return $clean === null ? '' : $clean;
    }

    private function callLanguageTool(string $text): array
    {
        $payload = [
            'language' => 'es',
            'text' => $text,
            'enabledOnly' => false,
            'level' => 'picky',
        ];

        $response = Http::asForm()
            ->timeout(15)
            ->withHeaders(['User-Agent' => 'Toolbox-OrthographyChecker/1.0'])
            ->post('https://api.languagetool.org/v2/check', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('No pudimos analizar el texto.');
        }

        return $response->json();
    }

    private function formatMatches(array $matches): array
    {
        $formatted = [];
        foreach (array_values($matches) as $index => $match) {
            $context = $match['context'] ?? [];
            $contextText = $context['text'] ?? '';
            $contextOffset = max(0, (int) ($context['offset'] ?? 0));
            $length = max(0, (int) ($context['length'] ?? $match['length'] ?? 0));
            $word = $this->extractSegment($contextText, $contextOffset, $length);
            $replacements = array_values(array_filter(array_unique(array_map(
                fn ($replacement) => $replacement['value'] ?? '',
                $match['replacements'] ?? []
            ))));

            $issueType = strtolower($match['rule']['issueType'] ?? 'other');

            $formatted[] = [
                'id' => $match['rule']['id'] ?? "orthography_match_{$index}",
                'message' => $match['message'] ?? '',
                'short_message' => $match['shortMessage'] ?? '',
                'issue_type' => $issueType,
                'issue_type_label' => $this->issueTypeLabel($issueType),
                'rule' => $match['rule']['id'] ?? '',
                'rule_url' => $match['rule']['urls'][0]['value'] ?? null,
                'replacements' => $replacements,
                'context_text' => $contextText,
                'offset' => $contextOffset,
                'length' => $length,
                'word' => $word,
                'sentence' => $match['sentence'] ?? '',
            ];
        }

        return $formatted;
    }

    private function extractSegment(string $text, int $offset, int $length): string
    {
        if ($offset < 0) {
            $offset = 0;
        }
        if ($length <= 0) {
            return '';
        }

        return mb_substr($text, $offset, $length) ?: '';
    }

    private function issueTypeLabel(string $type): string
    {
        return match ($type) {
            'misspelling' => 'Ortografía',
            'grammar' => 'Gramática',
            'punctuation' => 'Puntuación',
            'style' => 'Estilo',
            default => 'Otros',
        };
    }

    private function buildHtmlFromFallback(string $rawText, string $targetUrl): string
    {
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $rawText ?? '') ?? []));
        $bodyParts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '# ')) {
                $bodyParts[] = '<h1>' . e(substr($line, 2)) . '</h1>';
            } elseif (str_starts_with($line, '## ')) {
                $bodyParts[] = '<h2>' . e(substr($line, 3)) . '</h2>';
            } else {
                $bodyParts[] = '<p>' . e($line) . '</p>';
            }
        }

        $fallbackBody = implode("\n", $bodyParts) ?: '<p>Contenido no disponible.</p>';

        return <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{$targetUrl}</title>
</head>
<body>{$fallbackBody}</body>
</html>
HTML;
    }
}
