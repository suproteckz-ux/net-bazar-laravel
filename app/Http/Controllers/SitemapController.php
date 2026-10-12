<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Generates XML sitemaps with 24-hour file-cache.
 *
 * No Redis or cron required — works on shared hosting (Hoster.kz / Plesk).
 * Run `php artisan cache:clear` to force refresh after bulk catalog changes.
 *
 * Domain: config('netbazar.contact.site_url')
 * To switch domain: update that config key only — all sitemap URLs and robots.txt follow.
 * Cache keys are namespaced per domain so a domain change never serves stale XML.
 */
class SitemapController extends Controller
{
    private const PRODUCTS_PER_FILE = 500;
    private const CACHE_TTL         = 86400; // 24 hours in seconds

    // ── Public endpoints ──────────────────────────────────────────────────────

    /** GET /sitemap.xml — Sitemap Index */
    public function index(): Response
    {
        $xml = Cache::remember($this->cacheKey('index'), self::CACHE_TTL, function () {
            $base   = $this->base();
            $chunks = $this->productChunks();

            $sitemaps = [
                $base . '/sitemap-pages.xml',
                $base . '/sitemap-categories.xml',
            ];
            for ($i = 1; $i <= $chunks; $i++) {
                $sitemaps[] = $base . '/sitemap-products-' . $i . '.xml';
            }

            return $this->renderIndex($sitemaps);
        });

        return $this->xmlResponse($xml);
    }

    /** GET /sitemap-pages.xml — Static indexable pages */
    public function pages(): Response
    {
        $xml = Cache::remember($this->cacheKey('pages'), self::CACHE_TTL, function () {
            $base = $this->base();
            $urls = [
                $base . '/',
                $base . '/catalog',
                $base . '/about',
                $base . '/contacts',
                $base . '/delivery-payment',
            ];
            return $this->renderUrlset(array_map(fn($u) => ['loc' => $u], $urls));
        });

        return $this->xmlResponse($xml);
    }

    /** GET /sitemap-categories.xml — Canonical category slug pages */
    public function categories(): Response
    {
        $xml = Cache::remember($this->cacheKey('categories'), self::CACHE_TTL, function () {
            $base  = $this->base();
            // Only canonical slug-based category pages (not ?category= query params).
            // See SEO issue note in README: kaspi dynamic categories lack canonical slugs.
            $slugs = ['bytovaya-tehnika', 'kresla', 'kulery-dlya-vody', 'himiya', 'avtoaksessuary'];
            $urls  = array_map(fn($s) => ['loc' => $base . '/catalog/' . $s], $slugs);
            return $this->renderUrlset($urls);
        });

        return $this->xmlResponse($xml);
    }

    /** GET /sitemap-products-{page}.xml — Products chunk */
    public function products(int $page): Response
    {
        $chunks = $this->productChunks();

        if ($page < 1 || $page > $chunks) {
            abort(404);
        }

        $xml = Cache::remember($this->cacheKey('products:' . $page), self::CACHE_TTL, function () use ($page) {
            $base   = $this->base();
            $offset = ($page - 1) * self::PRODUCTS_PER_FILE;

            $rows = DB::table('products')
                ->where('present', 1)
                ->whereNotNull('url_slug')
                ->orderBy('last_imported_at', 'desc')
                ->orderBy('sku')
                ->select(['url_slug', 'last_imported_at'])
                ->limit(self::PRODUCTS_PER_FILE)
                ->offset($offset)
                ->get();

            $urls = $rows->map(fn($p) => [
                'loc'     => $base . '/product/' . $p->url_slug,
                'lastmod' => $this->toDate($p->last_imported_at),
            ])->all();

            return $this->renderUrlset($urls);
        });

        return $this->xmlResponse($xml);
    }

    /** GET /robots.txt — Dynamic robots with correct Sitemap domain */
    public function robots(): Response
    {
        $content = "User-agent: *\nDisallow:\n\nSitemap: " . $this->base() . "/sitemap.xml\n";
        return response($content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function base(): string
    {
        return rtrim(config('netbazar.contact.site_url', 'https://netbazar.kz'), '/');
    }

    /**
     * Domain-namespaced cache key.
     * Changing site_url automatically produces a fresh key, so stale XML from
     * the old domain is never served after a domain migration.
     */
    private function cacheKey(string $suffix): string
    {
        $host = parse_url($this->base(), PHP_URL_HOST) ?? 'default';
        return 'sitemap.' . $host . ':' . $suffix;
    }

    /** Number of product sitemap files needed (always ≥ 1). */
    private function productChunks(): int
    {
        return Cache::remember($this->cacheKey('product_chunks'), self::CACHE_TTL, function () {
            $count = DB::table('products')
                ->where('present', 1)
                ->whereNotNull('url_slug')
                ->count();
            return max(1, (int) ceil($count / self::PRODUCTS_PER_FILE));
        });
    }

    /** Format a MySQL timestamp to ISO-8601 date (Y-m-d). Null-safe. */
    private function toDate(?string $ts): ?string
    {
        if ($ts === null || $ts === '') {
            return null;
        }
        return substr($ts, 0, 10); // "2026-09-17 14:00:03" → "2026-09-17"
    }

    // ── XML renderers ─────────────────────────────────────────────────────────

    private function renderIndex(array $locs): string
    {
        $out = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $out[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($locs as $loc) {
            $out[] = '  <sitemap>';
            $out[] = '    <loc>' . $this->esc($loc) . '</loc>';
            $out[] = '  </sitemap>';
        }
        $out[] = '</sitemapindex>';
        return implode("\n", $out);
    }

    /**
     * @param array<int, array{loc: string, lastmod?: string|null}> $urls
     */
    private function renderUrlset(array $urls): string
    {
        $out = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $out[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $out[] = '  <url>';
            $out[] = '    <loc>' . $this->esc($url['loc']) . '</loc>';
            if (!empty($url['lastmod'])) {
                $out[] = '    <lastmod>' . $this->esc($url['lastmod']) . '</lastmod>';
            }
            $out[] = '  </url>';
        }
        $out[] = '</urlset>';
        return implode("\n", $out);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
