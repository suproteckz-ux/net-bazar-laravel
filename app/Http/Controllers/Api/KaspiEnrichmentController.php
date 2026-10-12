<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KaspiEnrichmentController extends Controller
{
    private const PARTNER_ID = 'Avtoximiya';
    private const CITY_ID = '750000000';
    private const FRAME_BASE = 'https://kaspi.kz/shop/kaspibutton/frame/';

    // ─── Endpoints ──────────────────────────────────────────────────────────

    public function candidates(): JsonResponse
    {
        $candidates = DB::table('kaspi_lookup as k')
            ->join('products as p', 'p.sku', '=', 'k.sku')
            ->leftJoin('kaspi_content as e', function ($join) {
                $join->on('e.sku', '=', 'k.sku')->on('e.url', '=', 'k.url');
            })
            ->where('p.present', 1)
            ->where('k.status', 'resolved')
            ->orderBy('k.sku')
            ->select(['k.sku', 'k.url', 'e.status as content_status'])
            ->get();

        $notFound = DB::table('kaspi_lookup')
            ->where('status', 'not_found')
            ->count();

        return response()->json([
            'candidates' => $candidates,
            'not_found'  => $notFound,
        ]);
    }

    public function storeContent(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        try {
            $result = $this->storePayload($payload);
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'Invalid request payload'], 422);
        }

        // Mirror Python: parser_pending cleared outside the main transaction
        if (in_array($result['status'], ['ready', 'pending_category'], true)) {
            DB::table('kaspi_work_queue')
                ->where('sku', $payload['sku'])
                ->update(['parser_pending' => 0]);
        }

        return response()->json($result);
    }

    public function widgetCandidates(): JsonResponse
    {
        $candidates = DB::table('products as p')
            ->join('kaspi_work_queue as q', 'q.sku', '=', 'p.sku')
            ->where('q.widget_pending', 1)
            ->orderBy('p.sku')
            ->select('p.sku')
            ->get()
            ->map(fn ($row) => ['sku' => $row->sku])
            ->values();

        return response()->json(['candidates' => $candidates]);
    }

    public function storeWidgetResult(Request $request): JsonResponse
    {
        $p = $request->json()->all();

        if (!is_array($p)) {
            return response()->json(['error' => 'Unprocessable Entity'], 422);
        }

        // Require exactly these keys (order-independent, matching Python set check)
        $required = ['city_id', 'detail', 'partner_id', 'sku', 'status', 'url'];
        $actualKeys = array_keys($p);
        sort($actualKeys);
        if ($actualKeys !== $required) {
            return response()->json(['error' => 'Unprocessable Entity'], 422);
        }

        $sku    = $p['sku'];
        $status = $p['status'];
        $url    = $p['url'];

        if (!is_string($sku) || !$sku || $sku !== trim($sku)) {
            return response()->json(['error' => 'Unprocessable Entity'], 422);
        }

        if ($p['partner_id'] !== self::PARTNER_ID || $p['city_id'] !== self::CITY_ID) {
            return response()->json(['error' => 'Unprocessable Entity'], 422);
        }

        $validStatuses = [
            'resolved', 'not_found', 'ambiguous', 'identity_mismatch',
            'invalid_url', 'invalid_response', 'captcha', 'network_error',
            'timeout', 'widget_error',
        ];
        if (!in_array($status, $validStatuses, true)) {
            return response()->json(['error' => 'Unprocessable Entity'], 422);
        }

        if (!is_string($p['detail']) || mb_strlen($p['detail']) > 1500) {
            return response()->json(['error' => 'Unprocessable Entity'], 422);
        }

        if ($status === 'resolved') {
            try {
                $this->validateKaspiProductUrl($url);
            } catch (\InvalidArgumentException) {
                return response()->json(['error' => 'Unprocessable Entity'], 422);
            }
        } elseif ($url !== null) {
            return response()->json(['error' => 'Unprocessable Entity'], 422);
        }

        if (!DB::table('products')->where('sku', $sku)->exists()) {
            return response()->json(['error' => 'Not Found'], 404);
        }

        DB::transaction(function () use ($sku, $status, $url, $p) {
            $old      = DB::table('kaspi_lookup')->where('sku', $sku)->first();
            $frameUrl = $this->computeFrameUrl($sku);

            if ($old) {
                DB::table('kaspi_lookup')->where('sku', $sku)->update([
                    'partner_id' => self::PARTNER_ID,
                    'city_id'    => self::CITY_ID,
                    'status'     => $status,
                    'url'        => $url,
                    'detail'     => $p['detail'],
                    'frame_url'  => $frameUrl,
                    'checked_at' => now(),
                    'attempts'   => DB::raw('attempts + 1'),
                ]);
            } else {
                DB::table('kaspi_lookup')->insert([
                    'sku'        => $sku,
                    'partner_id' => self::PARTNER_ID,
                    'city_id'    => self::CITY_ID,
                    'status'     => $status,
                    'url'        => $url,
                    'detail'     => $p['detail'],
                    'frame_url'  => $frameUrl,
                    'checked_at' => now(),
                    'attempts'   => 1,
                ]);
            }

            $terminalStatuses = [
                'resolved', 'not_found', 'ambiguous',
                'identity_mismatch', 'invalid_url', 'widget_error',
            ];
            if (in_array($status, $terminalStatuses, true)) {
                DB::table('kaspi_work_queue')->where('sku', $sku)->update(['widget_pending' => 0]);
            }

            // New URL resolved → re-queue parser
            if ($status === 'resolved' && (!$old || $old->url !== $url)) {
                DB::table('kaspi_work_queue')->where('sku', $sku)->update(['parser_pending' => 1]);
            }
        });

        return response()->json(['sku' => $sku, 'status' => $status]);
    }

    // ─── store_payload (mirrors enrichment.store_payload) ───────────────────

    private function storePayload(array $payload): array
    {
        $allowed = [
            'sku', 'url', 'description', 'attributes', 'images',
            'breadcrumbs', 'category_path', 'status', 'detail', 'source_sha256',
        ];

        if (array_diff(array_keys($payload), $allowed)) {
            throw new \InvalidArgumentException('Unexpected payload fields');
        }

        $sku = $payload['sku'] ?? null;
        if (!is_string($sku) || !$sku || $sku !== trim($sku)) {
            throw new \InvalidArgumentException('Exact string SKU required');
        }

        $url = $payload['url'] ?? null;
        $this->validateKaspiProductUrl($url);

        if (!in_array($payload['status'] ?? null, ['ready', 'pending_category', 'error'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        foreach (['description', 'detail', 'source_sha256'] as $key) {
            $val = $payload[$key] ?? '';
            if (!is_string($val) || mb_strlen($val) > 65000) {
                throw new \InvalidArgumentException("Invalid text: $key");
            }
        }

        $attrs = $payload['attributes'] ?? [];
        if (!is_array($attrs) || count($attrs) > 300) {
            throw new \InvalidArgumentException('Invalid attributes');
        }
        $cleanAttrs = [];
        foreach ($attrs as $a) {
            if (!is_array($a)) throw new \InvalidArgumentException('Invalid attribute');
            $aKeys = array_keys($a);
            sort($aKeys);
            if ($aKeys !== ['name', 'value']) throw new \InvalidArgumentException('Invalid attribute');
            if (!is_string($a['name']) || mb_strlen($a['name']) > 65000) throw new \InvalidArgumentException('Invalid attribute');
            if (!is_string($a['value']) || mb_strlen($a['value']) > 65000) throw new \InvalidArgumentException('Invalid attribute');
            $cleanAttrs[] = ['name' => $this->plain($a['name']), 'value' => $this->plain($a['value'])];
        }

        $images = $payload['images'] ?? [];
        if (!is_array($images) || count($images) > 100) {
            throw new \InvalidArgumentException('Invalid images');
        }
        foreach ($images as $imgUrl) {
            if (!is_string($imgUrl) || !$this->isValidImageUrl($imgUrl)) {
                throw new \InvalidArgumentException('Invalid images');
            }
        }
        $images = array_values(array_unique($images)); // deduplicate preserving order

        $crumbs = $payload['breadcrumbs'] ?? [];
        $path   = $this->categoryPath($crumbs);

        if (($payload['category_path'] ?? []) !== $path) {
            throw new \InvalidArgumentException('Unconfirmed category path');
        }

        $state = ($payload['status'] === 'error') ? 'error' : ($path ? 'ready' : 'pending_category');
        if ($state === 'error') {
            $path   = [];
            $crumbs = [];
        }

        $result = DB::transaction(function () use ($sku, $url, $payload, $cleanAttrs, $images, $path, $crumbs, $state) {
            $lookupUrl = DB::table('kaspi_lookup')
                ->where('sku', $sku)
                ->where('status', 'resolved')
                ->value('url');

            if ($lookupUrl === null || $lookupUrl !== $url) {
                throw new \InvalidArgumentException('SKU URL not confirmed or changed');
            }

            // A failed refresh must not erase previously obtained content
            if ($state === 'error') {
                $existingContent = DB::table('kaspi_content')
                    ->where('sku', $sku)
                    ->where('url', $url)
                    ->exists();
                if ($existingContent) {
                    return ['sku' => $sku, 'status' => 'error', 'category_path' => []];
                }
            }

            $contentData = [
                'url'                => $url,
                'description'        => $this->plain($payload['description'] ?? ''),
                'attributes_json'    => json_encode($cleanAttrs, JSON_UNESCAPED_UNICODE),
                'photos_json'        => json_encode($images, JSON_UNESCAPED_UNICODE),
                'category_path_json' => json_encode($path, JSON_UNESCAPED_UNICODE),
                'breadcrumbs_json'   => json_encode($crumbs, JSON_UNESCAPED_UNICODE),
                'status'             => $state,
                'detail'             => $payload['detail'] ?? '',
                'source_sha256'      => $payload['source_sha256'] ?? '',
                'received_at'        => now(),
            ];

            // Upsert without touching manual_description / manual_attributes_json / manual_photos_json
            if (DB::table('kaspi_content')->where('sku', $sku)->exists()) {
                DB::table('kaspi_content')->where('sku', $sku)->update($contentData);
            } else {
                DB::table('kaspi_content')->insert(array_merge(['sku' => $sku], $contentData));
            }

            $this->rebuildTree();

            return ['sku' => $sku, 'status' => $state, 'category_path' => array_column($path, 'name')];
        });

        return $result;
    }

    // ─── rebuild_tree (mirrors enrichment.rebuild_tree) ─────────────────────

    private function rebuildTree(): void
    {
        // Category IDs are deterministic (SHA-256 of name path), so unchanged paths
        // will get the same IDs after rebuild — save overrides to restore them.
        $overrides = DB::table('kaspi_category_overrides')->get()->toArray();

        // Disable FK checks: self-referential kaspi_categories and CASCADE targets
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('kaspi_category_overrides')->delete();
            DB::table('product_kaspi_categories')->delete();
            DB::table('kaspi_categories')->delete();

            $rows = DB::table('kaspi_content as e')
                ->join('kaspi_lookup as k', function ($join) {
                    $join->on('k.sku', '=', 'e.sku')
                        ->on('k.url', '=', 'e.url')
                        ->where('k.status', 'resolved');
                })
                ->join('products as p', function ($join) {
                    $join->on('p.sku', '=', 'e.sku')->where('p.present', 1);
                })
                ->where('e.status', 'ready')
                ->select(['e.sku', 'e.category_path_json'])
                ->get();

            foreach ($rows as $row) {
                $path   = json_decode($row->category_path_json, true) ?? [];
                $names  = [];
                $parent = null;

                foreach ($path as $node) {
                    $names[] = $node['name'];
                    $ident   = substr(hash('sha256', json_encode($names, JSON_UNESCAPED_UNICODE)), 0, 24);
                    DB::table('kaspi_categories')->insertOrIgnore([
                        'id'        => $ident,
                        'parent_id' => $parent,
                        'name'      => $node['name'],
                        'path'      => implode(' / ', $names),
                        'source_id' => $node['id'],
                    ]);
                    $parent = $ident;
                }

                if ($parent !== null) {
                    DB::table('product_kaspi_categories')->insert([
                        'sku'         => $row->sku,
                        'category_id' => $parent,
                    ]);
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Restore overrides for categories whose IDs survived the rebuild
        if ($overrides) {
            $existingIds = DB::table('kaspi_categories')->pluck('id')->flip()->toArray();
            foreach ($overrides as $override) {
                if (isset($existingIds[$override->category_id])) {
                    DB::table('kaspi_category_overrides')->insertOrIgnore((array) $override);
                }
            }
        }
    }

    // ─── category_path (mirrors enrichment.category_path) ───────────────────

    private function categoryPath(mixed $breadcrumbs): array
    {
        if (!is_array($breadcrumbs) || count($breadcrumbs) < 2 || count($breadcrumbs) > 30) {
            return [];
        }
        if (($breadcrumbs[0]['name'] ?? null) !== 'Kaspi Магазин') return [];
        if (($breadcrumbs[0]['url'] ?? null) !== '/shop/c/categories/') return [];

        foreach (array_slice($breadcrumbs, 1) as $node) {
            if (!is_array($node)) return [];
            foreach (['id', 'name', 'url'] as $key) {
                if (!isset($node[$key]) || !is_string($node[$key]) || !trim($node[$key])) return [];
            }
            if ($this->plain($node['name']) !== $node['name']) return [];
            if (!str_starts_with($node['url'], '/shop/c/') || !str_ends_with($node['url'], '/')) return [];
        }

        $nodes = array_slice($breadcrumbs, 1);
        $ids   = array_column($nodes, 'id');
        if (count(array_unique($ids)) !== count($ids)) return [];

        return $nodes;
    }

    // ─── Validation helpers ──────────────────────────────────────────────────

    private function validateKaspiProductUrl(mixed $url): void
    {
        if (!is_string($url)) throw new \InvalidArgumentException('Invalid product URL');
        $parsed = parse_url($url);
        if (!$parsed
            || ($parsed['scheme'] ?? '') !== 'https'
            || ($parsed['host'] ?? '') !== 'kaspi.kz'
            || !empty($parsed['query'])
            || !empty($parsed['fragment'])) {
            throw new \InvalidArgumentException('Invalid product URL');
        }
        if (!preg_match('#^/shop/p/[^/]+-\d+/$#', $parsed['path'] ?? '')) {
            throw new \InvalidArgumentException('Invalid product URL');
        }
    }

    private function isValidImageUrl(string $url): bool
    {
        $parsed = parse_url($url);
        return isset($parsed['scheme'], $parsed['host'], $parsed['path'])
            && $parsed['scheme'] === 'https'
            && $parsed['host'] === 'resources.cdn-kaspi.kz'
            && str_starts_with($parsed['path'], '/img/');
    }

    private function computeFrameUrl(string $sku): string
    {
        return self::FRAME_BASE . '?' . http_build_query([
            'merchantSku'  => $sku,
            'merchantCode' => self::PARTNER_ID,
            'city'         => self::CITY_ID,
            'template'     => 'button',
            'style'        => 'desktop',
        ]);
    }

    // plain() mirrors enrichment.plain(): strip HTML tags, normalize whitespace
    private function plain(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<(br|p|div|li)(\s[^>]*)?\/?>/iu', "\n", $value) ?? $value;
        $value = preg_replace('#</(p|div|li)>#iu', "\n", $value) ?? $value;
        $value = strip_tags($value);
        $lines = explode("\n", $value);
        $lines = array_map(
            fn ($line) => implode(' ', array_filter(preg_split('/\s+/u', $line) ?: [])),
            $lines
        );
        $lines = array_values(array_filter($lines, fn ($line) => $line !== ''));
        return implode("\n", $lines);
    }
}
