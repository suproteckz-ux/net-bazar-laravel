<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Handles 301 redirects for legacy Satu.kz URLs.
 *
 * Products:   /p{satu_id}-{slug}.html  →  /product/{url_slug}
 * Categories: /g{satu_id}-{slug}       →  /catalog/{url_slug} or /catalog?category={id}
 *
 * url_slug is resolved fresh from the DB on every request to avoid creating
 * redirect chains when url_slugs are generated after the redirect table was imported.
 *
 * Populate tables with: php artisan netbazar:import-satu-redirects
 */
class SatuRedirectController extends Controller
{
    public function product(string $satuId): RedirectResponse
    {
        $row = DB::table('satu_product_redirects')
            ->where('satu_id', (int) $satuId)
            ->select(['sku'])
            ->first();

        if ($row === null) {
            abort(404);
        }

        // Resolve current url_slug at request time — prevents chains if
        // url_slug was NULL at import time but has since been generated.
        $product = DB::table('products')
            ->where('sku', $row->sku)
            ->select(['url_slug', 'sku'])
            ->first();

        if ($product === null) {
            abort(404);
        }

        $dest = $product->url_slug ?? $product->sku;
        return redirect('/product/' . $dest, 301);
    }

    public function category(string $satuId): RedirectResponse
    {
        $row = DB::table('satu_category_redirects')
            ->where('satu_id', (int) $satuId)
            ->select(['kaspi_id', 'target'])
            ->first();

        if ($row === null) {
            abort(404);
        }

        // Resolve url_slug at request time to avoid chains.
        // /catalog?category={id} is intentionally NOT used as a fallback: when a kaspi category
        // later gets its url_slug, that ?category= URL would 301 to /catalog/{slug}, creating a
        // two-hop chain. If url_slug is not yet generated, return 404 until the slug is ready.
        if ($row->kaspi_id !== null) {
            $urlSlug = DB::table('kaspi_categories')
                ->where('id', $row->kaspi_id)
                ->value('url_slug');

            if ($urlSlug !== null) {
                return redirect('/catalog/' . $urlSlug, 301);
            }

            // url_slug not yet generated — 404 until generate-category-slugs is run
            abort(404);
        }

        // Static target (e.g. marketing page /catalog/bytovaya-tehnika) — direct canonical, no chain
        if ($row->target !== null) {
            return redirect($row->target, 301);
        }

        abort(404);
    }
}
