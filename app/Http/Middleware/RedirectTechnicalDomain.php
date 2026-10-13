<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permanently redirects legacy technical domain(s) to the canonical domain.
 *
 * Only the explicitly listed technical hosts in config('netbazar.tech_domains')
 * are ever redirected. Arbitrary Host values are ignored (no open redirect).
 *
 * Scope:
 *   - GET and HEAD requests only (POST/PATCH/PUT/DELETE are passed through).
 *   - /up health endpoint is skipped (monitoring must not follow redirects).
 *   - /api/* routes never enter this middleware (registered in web group only).
 *
 * Satu URL resolution:
 *   For /p{id}-*.html and /g{id}-* paths the middleware performs a best-effort
 *   DB lookup to collapse the domain-hop and the Satu-to-canonical hop into one
 *   single 301. Falls back to redirecting the original path if not found in DB.
 */
class RedirectTechnicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        // getHost() reads HTTP_HOST directly; X-Forwarded-Host is not trusted
        // unless TrustProxies is configured (it is not for this app).
        $host = $request->getHost();

        $techHosts = config('netbazar.tech_domains', ['netbazar.kz', 'www.netbazar.kz']);

        if (!in_array($host, (array) $techHosts, true)) {
            return $next($request);
        }

        // Only redirect idempotent methods; POSTing to net-bazar.kz would lose the body.
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $path = $request->getPathInfo();

        // Skip health endpoint — monitoring agents must not follow redirects.
        if ($path === '/up') {
            return $next($request);
        }

        $canonicalUrl = rtrim(config('netbazar.contact.site_url', 'https://net-bazar.kz'), '/');
        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST) ?? 'net-bazar.kz';

        // Loop guard: if site_url was misconfigured to point back at a tech domain, skip.
        if ($canonicalHost === $host) {
            return $next($request);
        }

        // For Satu legacy paths: resolve to the final canonical path when possible,
        // collapsing the domain redirect and the Satu redirect into one single hop.
        $resolvedPath = $this->resolveSatuPath($path);
        $finalPath    = $resolvedPath ?? $path;

        $location = 'https://' . $canonicalHost . $finalPath;

        $qs = $request->getQueryString();
        if ($qs !== null && $qs !== '') {
            $location .= '?' . $qs;
        }

        return redirect($location, 301);
    }

    /**
     * Resolve a Satu legacy path to its canonical Laravel equivalent.
     *
     * /p{satuId}-{slug}.html → /product/{url_slug}
     * /g{satuId}-{slug}      → /catalog/{url_slug} | static target
     *
     * Returns null if the path is not a Satu URL, is not in the redirect table,
     * or if the DB is unavailable — in all these cases the caller redirects the
     * original path to the canonical domain instead (still a correct redirect,
     * just one hop later the SatuRedirectController handles the second step).
     */
    private function resolveSatuPath(string $path): ?string
    {
        try {
            // Product: /p12345-some-slug.html
            if (preg_match('/^\/p(\d+)-[a-z0-9_-]+\.html$/i', $path, $m)) {
                $sku = DB::table('satu_product_redirects')
                    ->where('satu_id', (int) $m[1])
                    ->value('sku');

                if ($sku === null) {
                    return null;
                }

                $urlSlug = DB::table('products')
                    ->where('sku', $sku)
                    ->value('url_slug');

                return '/product/' . ($urlSlug ?? rawurlencode($sku));
            }

            // Category: /g12345-some-slug
            if (preg_match('/^\/g(\d+)-[a-z0-9_-]+$/i', $path, $m)) {
                $row = DB::table('satu_category_redirects')
                    ->where('satu_id', (int) $m[1])
                    ->select(['kaspi_id', 'target'])
                    ->first();

                if ($row === null) {
                    return null;
                }

                if ($row->kaspi_id !== null) {
                    $slug = DB::table('kaspi_categories')
                        ->where('id', $row->kaspi_id)
                        ->value('url_slug');
                    // If slug not yet generated, return null so the canonical domain
                    // handles the request (SatuRedirectController will 404 cleanly).
                    return $slug !== null ? '/catalog/' . $slug : null;
                }

                if ($row->target !== null) {
                    return $row->target; // e.g. /catalog/kresla
                }
            }
        } catch (\Throwable) {
            // DB unavailable or table missing — fall through to simple domain redirect.
        }

        return null;
    }
}
