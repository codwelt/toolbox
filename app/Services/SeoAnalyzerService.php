<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use function e;
use SimpleXMLElement;

class SeoAnalyzerService
{
    public function analyze(string $url): array
    {
        $normalized = $this->normalizeUrl($url);
        if (!$normalized) {
            throw new \InvalidArgumentException('URL inválida.');
        }

        [$html, $usedFallback] = $this->fetchHtml($normalized);
        $doc = $this->loadDocument($html);
        $xpath = new DOMXPath($doc);
        $host = parse_url($normalized, PHP_URL_HOST) ?? '';

        $htmlLower = strtolower($html);
        $meta = $this->extractMeta($doc, $xpath);
        $structure = $this->extractStructure($doc, $xpath);
        $links = $this->extractLinks($doc, $xpath, $host, $normalized);
        $structuredData = $this->extractStructuredData($doc, $xpath);
        $content = $this->extractContent($doc);
        $robotsData = $this->fetchRobotsAndSitemap($normalized);

        $checks = $this->buildChecks($meta, $structure, $links, $structuredData, $content, $robotsData, $htmlLower);
        $score = $this->calculateScore($checks);

        return [
            'url' => $normalized,
            'domain' => $host,
            'used_fallback' => $usedFallback,
            'title' => $meta['title'],
            'description' => $meta['description'],
            'canonical' => $meta['canonical'],
            'robots' => $meta['robots'],
            'lang' => $meta['lang'],
            'hreflangs' => $meta['hreflangs'],
            'viewport' => $meta['viewport'],
            'charset' => $meta['charset'],
            'h1s' => $structure['h1s'],
            'h2s' => $structure['h2s'],
            'images_count' => $structure['images'],
            'word_count' => $content['word_count'],
            'structured_data_types' => $structuredData,
            'checks' => $checks,
            'score' => $score,
            'robots_txt' => $robotsData['robots'] ?? null,
            'sitemap' => $robotsData['sitemap'] ?? null,
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
            'User-Agent' => 'Toolsbox-SEO-Checker/1.0',
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
        return [$this->buildHtmlFromFallback($fallbackText, $url), true];
    }

    private function loadDocument(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        libxml_clear_errors();
        return $doc;
    }

    private function extractMeta(DOMDocument $doc, DOMXPath $xpath): array
    {
        $titleNode = $doc->getElementsByTagName('title')->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : '';

        $description = $this->getMetaContent($xpath, 'description');
        $robots = strtolower($this->getMetaContent($xpath, 'robots'));
        $canonical = $this->getLinkHref($xpath, 'canonical');
        $lang = strtolower($doc->documentElement?->getAttribute('lang') ?? '');
        if (!$lang) {
            $htmlNode = $xpath->query('//html')->item(0);
            if ($htmlNode instanceof \DOMElement) {
                $lang = strtolower($htmlNode->getAttribute('lang') ?? '');
            }
        }
        if (!$lang) {
            $ogLocale = $this->getMetaProperty($xpath, 'og:locale');
            if ($ogLocale) {
                $lang = strtolower(substr($ogLocale, 0, 2));
            }
        }
        $viewport = $this->getMetaContent($xpath, 'viewport');
        $charset = '';
        $charsetNode = $doc->getElementsByTagName('meta');
        foreach ($charsetNode as $meta) {
            if ($meta->hasAttribute('charset')) {
                $charset = strtolower($meta->getAttribute('charset'));
                break;
            }
        }

        $hreflangs = [];
        foreach ($xpath->query("//link[@rel='alternate'][@hreflang]") as $link) {
            $hreflangs[] = strtolower($link->getAttribute('hreflang'));
        }
        $hreflangs = array_values(array_unique(array_filter($hreflangs)));

        return [
            'title' => $title,
            'description' => $description,
            'robots' => $robots,
            'canonical' => $canonical,
            'lang' => $lang,
            'hreflangs' => $hreflangs,
            'viewport' => $viewport,
            'charset' => $charset,
        ];
    }

    private function extractStructure(DOMDocument $doc, DOMXPath $xpath): array
    {
        $h1s = [];
        foreach ($doc->getElementsByTagName('h1') as $h1) {
            $text = trim($h1->textContent);
            if ($text) {
                $h1s[] = $text;
            }
        }
        $h2s = [];
        foreach ($doc->getElementsByTagName('h2') as $h2) {
            $text = trim($h2->textContent);
            if ($text) {
                $h2s[] = $text;
            }
        }
        $images = $doc->getElementsByTagName('img')->length;

        return [
            'h1s' => $h1s,
            'h2s' => $h2s,
            'images' => $images,
        ];
    }

    private function extractLinks(DOMDocument $doc, DOMXPath $xpath, string $host, string $baseUrl): array
    {
        $internal = 0;
        $external = 0;
        foreach ($doc->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            if (!$href || Str::startsWith($href, ['#', 'mailto:', 'tel:'])) {
                continue;
            }
            if (Str::startsWith($href, '//')) {
                $href = 'https:' . $href;
            }
            $linkHost = '';
            if (Str::startsWith($href, ['http://', 'https://'])) {
                $linkHost = parse_url($href, PHP_URL_HOST) ?: '';
            }
            if (!$linkHost) {
                $internal++;
            } else {
                $linkHost === $host ? $internal++ : $external++;
            }
        }

        return [
            'internal' => $internal,
            'external' => $external,
        ];
    }

    private function extractStructuredData(DOMDocument $doc, DOMXPath $xpath): array
    {
        $types = [];
        foreach ($xpath->query("//script[@type='application/ld+json']") as $script) {
            $json = $script->textContent;
            try {
                $parsed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                $this->collectTypes($parsed, $types);
            } catch (\Throwable $e) {
                // ignore malformed json
            }
        }
        return array_values(array_unique(array_filter($types)));
    }

    private function collectTypes($value, array &$types): void
    {
        if (is_array($value)) {
            if (isset($value['@type'])) {
                $types[] = is_array($value['@type']) ? implode(',', $value['@type']) : $value['@type'];
            }
            foreach ($value as $v) {
                $this->collectTypes($v, $types);
            }
        }
    }

    private function extractContent(DOMDocument $doc): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $doc->textContent ?? ''));
        $words = $text ? preg_split('/\s+/', $text) : [];
        return [
            'word_count' => $words ? count($words) : 0,
        ];
    }

    private function fetchRobotsAndSitemap(string $url): array
    {
        $origin = '';
        $robotsInfo = [
            'url' => null,
            'status' => null,
            'allows' => [],
            'disallows' => [],
        ];
        $sitemapInfo = [
            'url' => null,
            'status' => null,
            'urls_count' => 0,
            'first_lastmod' => null,
            'last_lastmod' => null,
        ];

        try {
            $base = parse_url($url);
            $origin = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '');
            $robotsUrl = $origin . '/robots.txt';
            $robotsInfo['url'] = $robotsUrl;
            $resp = Http::timeout(6)->get($robotsUrl);
            $robotsInfo['status'] = $resp->status();
            if ($resp->successful()) {
                $lines = preg_split('/\r?\n/', $resp->body());
                $sitemaps = [];
                foreach ($lines as $line) {
                    $trim = trim($line);
                    if (stripos($trim, 'Sitemap:') === 0) {
                        $sitemaps[] = trim(substr($trim, 8));
                    } elseif (stripos($trim, 'Disallow:') === 0) {
                        $robotsInfo['disallows'][] = trim(substr($trim, 9));
                    } elseif (stripos($trim, 'Allow:') === 0) {
                        $robotsInfo['allows'][] = trim(substr($trim, 6));
                    }
                }
                $sitemapUrl = $sitemaps[0] ?? null;
                if ($sitemapUrl) {
                    $sitemapInfo = array_merge($sitemapInfo, $this->analyzeSitemap($sitemapUrl));
                } else {
                    // Probar rutas comunes
                    $sitemapInfo = $this->tryDefaultSitemaps($origin, $sitemapInfo);
                }
            }
        } catch (\Throwable $e) {
            // ignore errors; return partial data
        }

        // Si robots falló o no devolvió sitemap, intenta rutas por defecto
        if (!$sitemapInfo['url']) {
            $sitemapInfo = $this->tryDefaultSitemaps($origin ?? '', $sitemapInfo);
        }

        return ['robots' => $robotsInfo, 'sitemap' => $sitemapInfo];
    }

    private function tryDefaultSitemaps(string $origin, array $baseInfo): array
    {
        if (!$origin) {
            return $baseInfo;
        }

        $candidates = [
            rtrim($origin, '/') . '/sitemap_index.xml',
            rtrim($origin, '/') . '/sitemap.xml',
        ];

        foreach ($candidates as $candidate) {
            $info = $this->analyzeSitemap($candidate);
            if ($info['status'] && $info['status'] < 400) {
                return $info;
            }
        }

        return $baseInfo;
    }

    private function analyzeSitemap(string $sitemapUrl): array
    {
        $info = [
            'url' => $sitemapUrl,
            'status' => null,
            'urls_count' => 0,
            'first_lastmod' => null,
            'last_lastmod' => null,
        ];

        try {
            $resp = Http::timeout(8)->get($sitemapUrl);
            $info['status'] = $resp->status();
            if (!$resp->successful()) {
                return $info;
            }

            $xml = @simplexml_load_string($resp->body());
            if ($xml instanceof SimpleXMLElement) {
                // Sitemap index
                if ($xml->getName() === 'sitemapindex') {
                    $sitemaps = [];
                    foreach ($xml->sitemap as $sm) {
                        $loc = (string) ($sm->loc ?? '');
                        if ($loc) {
                            $sitemaps[] = trim($loc);
                        }
                    }
                    // Analyze first sitemap in index
                    if (!empty($sitemaps)) {
                        return $this->analyzeSitemap($sitemaps[0]);
                    }
                } elseif ($xml->getName() === 'urlset') {
                    $count = 0;
                    $lastmods = [];
                    foreach ($xml->url as $u) {
                        $count++;
                        if (isset($u->lastmod)) {
                            $lastmods[] = (string) $u->lastmod;
                        }
                    }
                    sort($lastmods);
                    $info['urls_count'] = $count;
                    $info['first_lastmod'] = $lastmods[0] ?? null;
                    $info['last_lastmod'] = $lastmods ? $lastmods[count($lastmods) - 1] : null;
                }
            }
        } catch (\Throwable $e) {
            // ignore malformed sitemap
        }

        return $info;
    }

    private function buildChecks(array $meta, array $structure, array $links, array $structuredData, array $content, array $robotsData, string $htmlLower): array
    {
        $checks = [];
        $add = function ($label, $ok, $detail, $weight) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => (bool) $ok, 'detail' => $detail, 'weight' => $weight];
        };

        $add('Título (50-60 caracteres)', strlen($meta['title']) >= 30 && strlen($meta['title']) <= 65, $meta['title'] ?: 'Sin título', 2);
        $add('Meta descripción (110-160 caracteres)', strlen($meta['description']) >= 80 && strlen($meta['description']) <= 170, $meta['description'] ?: 'Sin meta description', 2);
        $add('Canonical presente', !empty($meta['canonical']), $meta['canonical'] ?: 'Sin canonical', 2);
        $add('Meta robots permite indexar', $meta['robots'] ? !str_contains($meta['robots'], 'noindex') : true, $meta['robots'] ?: 'No especificado', 1.5);
        $add('H1 presente y único', count($structure['h1s']) === 1, $structure['h1s'] ? implode(' | ', $structure['h1s']) : 'Sin H1', 1.5);
        $add('H2 presentes', count($structure['h2s']) >= 2, $structure['h2s'] ? implode(' | ', array_slice($structure['h2s'], 0, 3)) : 'Sin H2', 1);
        $add('Contenido visible mínimo (>=150 palabras)', $content['word_count'] >= 150, $content['word_count'] . ' palabras', 1.5);
        $add('Imágenes presentes (alt no evaluado)', $structure['images'] > 0, $structure['images'] . ' imágenes', 0.5);
        $add('Enlaces internos (>=5)', $links['internal'] >= 5, $links['internal'] . ' enlaces internos', 1);
        $add('Enlaces externos presentes', $links['external'] >= 1, $links['external'] . ' enlaces externos', 0.5);
        $add('Open Graph básico', $meta['title'] && $meta['description'], $meta['title'] ? 'OG puede generarse desde title/description' : 'Faltan OG', 1);
        $add('Meta viewport móvil', Str::contains(strtolower($meta['viewport']), 'width=device-width'), $meta['viewport'] ?: 'Sin viewport', 1);
        $add('Meta charset declarada', !empty($meta['charset']), $meta['charset'] ?: 'Sin charset', 0.5);
        $add('Atributo lang en <html>', !empty($meta['lang']), $meta['lang'] ?: 'Sin lang', 0.5);
        $add('Hreflang declarado', count($meta['hreflangs']) > 0, $meta['hreflangs'] ? implode(', ', $meta['hreflangs']) : 'Sin hreflang', 0.5);
        $add('Structured data (JSON-LD)', count($structuredData) > 0, $structuredData ? implode(', ', array_slice($structuredData, 0, 3)) : 'No detectado', 1);

        $sitemapUrl = $robotsData['sitemap']['url'] ?? '';
        $sitemapStatus = $robotsData['sitemap']['status'] ?? null;
        $sitemapHint = $sitemapUrl || str_contains($htmlLower, 'sitemap');
        $add(
            'Sitemap referenciado',
            $sitemapHint && $sitemapStatus && $sitemapStatus < 400,
            $sitemapUrl ?: 'No se detectó sitemap en robots ni en HTML',
            0.5
        );

        return $checks;
    }

    private function calculateScore(array $checks): int
    {
        $total = array_reduce($checks, fn($acc, $c) => $acc + $c['weight'], 0);
        $ok = array_reduce($checks, fn($acc, $c) => $acc + ($c['ok'] ? $c['weight'] : 0), 0);
        return (int) round(($ok / max($total, 1)) * 100);
    }

    private function getMetaContent(DOMXPath $xpath, string $name): string
    {
        $nodes = $xpath->query("//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$name}']");
        if ($nodes->length) {
            return trim($nodes->item(0)->getAttribute('content') ?? '');
        }
        return '';
    }

    private function getLinkHref(DOMXPath $xpath, string $rel): string
    {
        $nodes = $xpath->query("//link[@rel='{$rel}']");
        if ($nodes->length) {
            return trim($nodes->item(0)->getAttribute('href') ?? '');
        }
        return '';
    }

    private function getMetaProperty(DOMXPath $xpath, string $property): string
    {
        $nodes = $xpath->query("//meta[translate(@property, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$property}']");
        if ($nodes->length) {
            return trim($nodes->item(0)->getAttribute('content') ?? '');
        }
        return '';
    }

    private function buildHtmlFromFallback(string $rawText, string $targetUrl): string
    {
        $lines = collect(preg_split('/\r?\n/', $rawText ?? '') ?: [])->map(fn($l) => trim($l))->filter()->values();
        $fallbackTitle = '';
        $fallbackDescription = '';
        $robots = '';
        $viewport = '';
        $charset = '';
        $hreflangs = [];
        $bodyParts = [];

        $guessLangFromHost = function (string $host) {
            $tld = Str::of($host)->afterLast('.')->lower();
            $map = [
                'es' => 'es',
                'mx' => 'es',
                'ar' => 'es',
                'cl' => 'es',
                'co' => 'es',
                'pe' => 'es',
                'uy' => 'es',
                've' => 'es',
                'fr' => 'fr',
                'de' => 'de',
                'it' => 'it',
                'pt' => 'pt',
                'br' => 'pt',
                'nl' => 'nl',
            ];
            return $map[$tld] ?? '';
        };

        foreach ($lines as $line) {
            if (!$fallbackTitle) {
                if (Str::startsWith(Str::lower($line), 'title:')) {
                    $fallbackTitle = trim(Str::after($line, ':'));
                } elseif (Str::startsWith($line, '# ')) {
                    $fallbackTitle = trim(Str::after($line, '# '));
                }
            }
            if (!$fallbackDescription && strlen($line) > 20 && !Str::startsWith($line, '#')) {
                $fallbackDescription = $line;
            }
            if (!$robots && Str::contains(Str::lower($line), 'robots')) {
                $robots = trim(Str::after($line, ': ')) ?: 'index,follow';
            }
            if (!$viewport && Str::contains(Str::lower($line), 'viewport')) {
                $viewport = trim(Str::after($line, ': '));
            }
            if (!$charset && Str::contains(Str::lower($line), 'charset')) {
                $charset = trim(Str::after($line, ': '));
            }
            if (preg_match('/hreflang[:=\s-]+([a-z-]+)/i', $line, $m)) {
                $hreflangs[] = strtolower($m[1]);
            }
            if (Str::startsWith($line, '# ')) {
                $bodyParts[] = '<h1>' . e(Str::after($line, '# ')) . '</h1>';
            } elseif (Str::startsWith($line, '## ')) {
                $bodyParts[] = '<h2>' . e(Str::after($line, '## ')) . '</h2>';
            } elseif (Str::startsWith($line, '### ')) {
                $bodyParts[] = '<h3>' . e(Str::after($line, '### ')) . '</h3>';
            } else {
                $bodyParts[] = '<p>' . e($line) . '</p>';
            }
        }

        if (!$fallbackTitle) {
            $fallbackTitle = $targetUrl;
        }
        if (!$fallbackDescription && $lines->count()) {
            $fallbackDescription = $lines->first();
        }
        $robots = $robots ?: 'index,follow';
        $viewport = $viewport ?: 'width=device-width, initial-scale=1';
        $charset = $charset ?: 'utf-8';

        $lang = '';
        try {
            $lang = $guessLangFromHost(parse_url($targetUrl, PHP_URL_HOST) ?? '');
        } catch (\Throwable $e) {
            $lang = '';
        }

        if (!collect($bodyParts)->first(fn($p) => Str::startsWith($p, '<h1'))) {
            array_unshift($bodyParts, '<h1>' . e($fallbackTitle) . '</h1>');
        }
        if (!collect($bodyParts)->first(fn($p) => Str::startsWith($p, '<h2')) && $fallbackDescription) {
            array_splice($bodyParts, 1, 0, ['<h2>' . e(Str::limit($fallbackDescription, 120)) . '</h2>']);
        }

        $hreflangLinks = collect($hreflangs)->unique()->map(function ($hl) use ($targetUrl) {
            return '<link rel="alternate" hreflang="' . e($hl) . '" href="' . e($targetUrl) . '" />';
        })->implode("\n");

        $langAttr = $lang ? 'lang="' . e($lang) . '"' : '';
        $bodyContent = $bodyParts ? implode("\n", $bodyParts) : '';

        return <<<HTML
        <html {$langAttr}>
            <head>
                <meta charset="{$charset}" />
                <title>{$fallbackTitle}</title>
                <meta name="description" content="{$fallbackDescription}" />
                <meta name="robots" content="{$robots}" />
                <meta name="viewport" content="{$viewport}" />
                <link rel="canonical" href="{$targetUrl}" />
                {$hreflangLinks}
            </head>
            <body>
                {$bodyContent}
            </body>
        </html>
        HTML;
    }
}
