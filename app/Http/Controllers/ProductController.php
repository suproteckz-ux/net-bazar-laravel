<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    /**
     * Three-step lookup — no redirect chains:
     *   1. url_slug match   → 200 (canonical SEO URL)
     *   2. raw sku match    → 301 to /product/{url_slug}  (old bookmark / import link)
     *   3. alias match      → 301 directly to target's url_slug (never via raw sku)
     *   4. 404
     */
    public function show(string $param)
    {
        // Step 1 — url_slug is the canonical URL, serve it directly.
        $slugOwner = DB::table('products')
            ->where('url_slug', $param)
            ->value('sku');
        if ($slugOwner !== null) {
            return $this->renderProduct($slugOwner);
        }

        // Step 2 — raw SKU: redirect to the product's own url_slug (301, no chain).
        $byRawSku = DB::table('products')
            ->where('sku', $param)
            ->select(['sku', 'url_slug'])
            ->first();
        if ($byRawSku) {
            if ($byRawSku->url_slug) {
                return redirect()->route('product.show', ['sku' => $byRawSku->url_slug], 301);
            }
            // Fallback: url_slug not yet generated (should not occur after migration).
            return $this->renderProduct($byRawSku->sku);
        }

        // Step 3 — alias: resolve target in one hop, redirect to its url_slug.
        $newSku = DB::table('catalog_sku_aliases')
            ->where('old_sku', $param)
            ->value('new_sku');
        if ($newSku !== null) {
            $target = DB::table('products')
                ->where('sku', $newSku)
                ->select(['sku', 'url_slug'])
                ->first();
            if ($target) {
                $dest = $target->url_slug ?? $target->sku;
                return redirect()->route('product.show', ['sku' => $dest], 301);
            }
        }

        abort(404, 'Товар не найден.');
    }

    // ── Internal renderer ─────────────────────────────────────────────────────

    private function renderProduct(string $sku)
    {
        // Load product with enrichment and stock in one query.
        // kaspi_confirmed_url comes from kaspi_lookup (status=resolved) and gets ?m=PARTNER_ID
        // appended in the view — matches the original Python app.py L161 behaviour.
        $product = DB::table('products as p')
            ->leftJoin('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
            ->leftJoin('stock_pp3 as s', 's.sku', '=', 'p.sku')
            ->leftJoin('source_categories as sc', 'sc.id', '=', 'p.source_category_id')
            ->leftJoin('kaspi_lookup as kl', function ($join) {
                $join->on('kl.sku', '=', 'p.sku')->where('kl.status', '=', 'resolved');
            })
            ->where('p.sku', $sku)
            ->select(
                'p.sku', 'p.url_slug', 'p.name', 'p.brand', 'p.price_kzt', 'p.present',
                'p.source_photos_json', 'p.editorial_photos_json', 'p.source_category_id',
                'p.editorial_description',
                'sc.name as category_name', 'sc.path as category_path',
                'kc.photos_json as kaspi_photos_json',
                'kc.manual_photos_json',
                'kc.description as kaspi_description',
                'kc.attributes_json',
                'kc.status as kaspi_status',
                'kl.url as kaspi_confirmed_url',
                's.state as stock_state',
                's.quantity as stock_quantity',
                's.preorder_days',
                'p.available'
            )
            ->first();

        if (!$product) {
            abort(404, "Товар с артикулом «{$sku}» не найден.");
        }

        // Same orderable condition used by CartController::store() — single source of truth.
        // present=1 AND (available=1 OR stock_pp3.state='preorder')
        $isOrderable = (bool) ($product->present && ((bool) $product->available || $product->stock_state === 'preorder'));

        $photos      = $this->resolvePhotos($product);
        $attributes  = $this->parseAttributes($product->attributes_json ?? null);
        $stockLabel  = $this->stockLabel($product);
        $kaspiCrumbs = $this->resolveKaspiCrumbs($sku);

        return view('product.show', compact('product', 'photos', 'attributes', 'stockLabel', 'kaspiCrumbs', 'isOrderable'));
    }

    /**
     * Load Kaspi category ancestors for this SKU, root→leaf order, with override display names.
     * Mirrors the same product_kaspi_categories + kaspi_category_overrides join used in the catalog.
     */
    private function resolveKaspiCrumbs(string $sku): array
    {
        if (!Schema::hasTable('kaspi_category_overrides')) {
            return DB::select("
                WITH RECURSIVE chain(id, parent_id) AS (
                    SELECT kc.id, kc.parent_id
                    FROM product_kaspi_categories pkc
                    JOIN kaspi_categories kc ON kc.id = pkc.category_id
                    WHERE pkc.sku = ?
                    UNION ALL
                    SELECT kc.id, kc.parent_id
                    FROM kaspi_categories kc
                    JOIN chain c ON c.parent_id = kc.id
                )
                SELECT kc.id, kc.name, kc.url_slug
                FROM chain c
                JOIN kaspi_categories kc ON kc.id = c.id
                ORDER BY LENGTH(kc.path)
            ", [$sku]);
        }

        return DB::select("
            WITH RECURSIVE chain(id, parent_id) AS (
                SELECT kc.id, kc.parent_id
                FROM product_kaspi_categories pkc
                JOIN kaspi_categories kc ON kc.id = pkc.category_id
                WHERE pkc.sku = ?
                UNION ALL
                SELECT kc.id, kc.parent_id
                FROM kaspi_categories kc
                JOIN chain c ON c.parent_id = kc.id
            )
            SELECT kc.id, COALESCE(o.display_name, kc.name) AS name, kc.url_slug
            FROM chain c
            JOIN kaspi_categories kc ON kc.id = c.id
            LEFT JOIN kaspi_category_overrides o ON o.category_id = kc.id
            ORDER BY LENGTH(kc.path)
        ", [$sku]);
    }

    private function resolvePhotos(object $product): array
    {
        foreach (['editorial_photos_json', 'manual_photos_json', 'kaspi_photos_json', 'source_photos_json'] as $field) {
            if (!empty($product->$field) && $product->$field !== '[]') {
                $arr = json_decode($product->$field, true);
                if (!empty($arr)) {
                    return array_values(array_map(
                        fn ($p) => (str_starts_with($p, 'http') || str_starts_with($p, '/')) ? $p : '/storage/' . $p,
                        $arr
                    ));
                }
            }
        }
        return [];
    }

    private function parseAttributes(?string $json): array
    {
        if (empty($json) || $json === '[]' || $json === '{}') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        // Handle both [{name:..., value:...}] and {key: value} formats
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['name'])) {
                $result[] = ['name' => $value['name'], 'value' => $value['value'] ?? ''];
            } elseif (is_string($value)) {
                $result[] = ['name' => $key, 'value' => $value];
            }
        }
        return $result;
    }

    private function stockLabel(object $product): string
    {
        $state = $product->stock_state ?? null;
        $days  = (int)($product->preorder_days ?? 0);

        if ($state === 'in_stock') {
            return 'В наличии';
        }
        if ($state === 'preorder') {
            return $days > 0 ? "Под заказ {$days} дн." : 'Под заказ';
        }
        if ($state === 'out_of_stock') {
            return 'Нет в наличии';
        }
        return '';
    }
}
