<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Download and apply the hourly MarketRadar/Kaspi merchant catalog XML to MySQL.
 *
 * Matches the behaviour of catalog_sync.py + stock_import.py from the original Python project:
 *  - Kaspi catalog XML format (kaspiShopping namespace, merchantid=Avtoximiya)
 *  - PP3 warehouse availability only
 *  - Upserts products (name/brand/price/qty/available/present + last_imported_at)
 *  - Upserts stock_pp3 (full row)
 *  - Rejects feeds shorter than 90% of the historical maximum
 *  - Never deletes products; existing editorial/manual columns are not touched
 *  - Marks SKUs absent from the feed as out_of_stock (two-phase present_export flag)
 *  - Logs every run (success or error) to catalog_sync_runs
 */
class CatalogImport extends Command
{
    protected $signature = 'netbazar:catalog-import
        {--dry-run : Validate feed without writing to database}
        {--file=   : Path to a local XML file instead of downloading from the feed URL}';

    protected $description = 'Download and apply the hourly MarketRadar Kaspi catalog XML feed';

    private const MERCHANT_ID = 'Avtoximiya';
    private const MAX_BYTES   = 20 * 1024 * 1024; // 20 MB
    private const CHUNK_SIZE  = 500;

    public function handle(): int
    {
        $file    = $this->option('file');
        $dry     = (bool) $this->option('dry-run');
        $feedUrl = config('netbazar.market_radar_feed_url');

        if (!$file && !$feedUrl) {
            $this->error('MARKET_RADAR_FEED_URL is not set in .env');
            return 1;
        }

        // Step 1: Download
        try {
            $raw = $file
                ? $this->readFile($file)
                : $this->download($feedUrl);
        } catch (\Throwable $e) {
            $msg = 'Download failed: ' . $e->getMessage();
            $this->error($msg);
            $this->logRun('error', 0, $msg);
            return 1;
        }

        // Step 2: Parse
        try {
            $offers = $this->parseFeed($raw);
        } catch (\Throwable $e) {
            $msg = 'Parse error: ' . $e->getMessage();
            $this->error($msg);
            $this->logRun('error', 0, $msg, hash('sha256', $raw));
            return 1;
        }

        $count = count($offers);
        $this->line("Feed parsed: {$count} offers");

        // Step 3: Count validation — reject feeds that are suspiciously short.
        // Uses config baseline so tests can override it without touching code.
        $baseline    = (int) config('netbazar.catalog_baseline_offers', 3069);
        $previousMax = (int) max($baseline, DB::table('catalog_sync_runs')->max('offer_count') ?? 0);
        $minimum     = (int) ceil($previousMax * 0.9);

        if ($count < $minimum) {
            $msg = "Feed too short: {$count} offers, minimum {$minimum}. Catalog unchanged.";
            $this->error($msg);
            $this->logRun('rejected', $count, $msg, hash('sha256', $raw));
            return 1;
        }

        if ($dry) {
            $this->info("[DRY RUN] Feed valid: {$count} offers. No DB writes.");
            return 0;
        }

        // Step 4: Apply atomically
        $sha256 = hash('sha256', $raw);
        try {
            [$updated, $inserted, $stats] = $this->applyFeed($offers, $sha256);
            $this->info(
                "Import complete: {$count} offers"
                . " ({$inserted} new, {$updated} updated"
                . ", {$stats['zeroed_count']} zeroed"
                . ", {$stats['restored_count']} restored"
                . ", {$stats['absent_count']} absent)"
            );

            // Step 5: Generate URL slugs for any newly inserted products.
            // Runs only when the feed introduced new SKUs; skipped on stock-only updates.
            // The command is idempotent and only touches products with url_slug IS NULL,
            // so existing slugs are never overwritten.
            if ($inserted > 0) {
                $this->line("  Generating URL slugs for {$inserted} new product(s)…");
                $this->call('netbazar:generate-url-slugs');
            }

            return 0;
        } catch (\Throwable $e) {
            $msg = 'Import failed: ' . $e->getMessage();
            $this->error($msg);
            $this->logRun('error', $count, $msg, $sha256);
            return 1;
        }
    }

    private function readFile(string $path): string
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }
        $raw = file_get_contents($path);
        if ($raw === false || strlen($raw) === 0) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }
        return $raw;
    }

    private function download(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method'     => 'GET',
                'timeout'    => 60,
                'user_agent' => 'NetBazar-Laravel/1.0',
            ],
            'ssl' => ['verify_peer' => true],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new \RuntimeException("HTTP request failed for feed URL");
        }
        if (strlen($raw) > self::MAX_BYTES) {
            throw new \RuntimeException("Feed response exceeds " . (self::MAX_BYTES / 1024 / 1024) . " MB");
        }
        if (strlen($raw) === 0) {
            throw new \RuntimeException("Empty feed response");
        }
        return $raw;
    }

    /**
     * Parse the Kaspi merchant catalog XML.
     * Format: <kaspiShopping:kaspi_catalog xmlns:kaspiShopping="kaspiShopping">
     *   <kaspiShopping:merchantid>Avtoximiya</kaspiShopping:merchantid>
     *   <kaspiShopping:offers>
     *     <kaspiShopping:offer sku="123">
     *       <kaspiShopping:model>Name</kaspiShopping:model>
     *       <kaspiShopping:brand>Brand</kaspiShopping:brand>
     *       <kaspiShopping:price>5000</kaspiShopping:price>
     *       <kaspiShopping:availabilities>
     *         <kaspiShopping:availability storeId="PP3" stockCount="10" preOrder="0" available="yes"/>
     *       </kaspiShopping:availabilities>
     *     </kaspiShopping:offer>
     *   </kaspiShopping:offers>
     * </kaspiShopping:kaspi_catalog>
     *
     * @return array<string, array{name: string, brand: string, price: string, raw_stock: int,
     *                             preorder_days: int, source_available: int, quantity: int, state: string}>
     */
    private function parseFeed(string $raw): array
    {
        if (strlen($raw) > self::MAX_BYTES
            || stripos($raw, '<!DOCTYPE') !== false
            || stripos($raw, '<!ENTITY') !== false
            || str_contains($raw, "\x00")) {
            throw new \RuntimeException('Oversized, DTD, entity or NUL-containing feed rejected');
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded    = $dom->loadXML($raw, LIBXML_NONET);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();

        if (!$loaded) {
            $msg = !empty($xmlErrors) ? trim($xmlErrors[0]->message) : 'unknown';
            throw new \RuntimeException("XML parse error: {$msg}");
        }

        $root  = $dom->documentElement;
        $nsUri = $root->namespaceURI ?? '';
        $nsUri = $nsUri ?: 'kaspiShopping';

        if ($root->localName !== 'kaspi_catalog') {
            throw new \RuntimeException("Expected root <kaspi_catalog>, got <{$root->localName}>");
        }

        $merchantNodes = $dom->getElementsByTagNameNS($nsUri, 'merchantid');
        $merchantId    = $merchantNodes->length > 0 ? trim($merchantNodes->item(0)->textContent) : '';
        if (!$merchantId) {
            $merchantId = trim($dom->getElementsByTagName('merchantid')->item(0)?->textContent ?? '');
        }
        if ($merchantId !== self::MERCHANT_ID) {
            throw new \RuntimeException("Merchant ID mismatch: expected '" . self::MERCHANT_ID . "', got '{$merchantId}'");
        }

        $offerNodes = $dom->getElementsByTagNameNS($nsUri, 'offer');
        if ($offerNodes->length === 0) {
            $offerNodes = $dom->getElementsByTagName('offer');
        }
        if ($offerNodes->length === 0) {
            throw new \RuntimeException('No <offer> elements found in feed');
        }

        $offers = [];
        foreach ($offerNodes as $el) {
            /** @var \DOMElement $el */
            $sku = trim($el->getAttribute('sku'));
            if (!$sku) {
                throw new \RuntimeException('Offer with empty sku attribute');
            }
            if (isset($offers[$sku])) {
                throw new \RuntimeException("Duplicate SKU in feed: {$sku}");
            }

            $get = static function (string $tag) use ($el, $nsUri): string {
                $nodes = $el->getElementsByTagNameNS($nsUri, $tag);
                if ($nodes->length === 0) {
                    $nodes = $el->getElementsByTagName($tag);
                }
                return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : '';
            };

            $name     = $get('model');
            $brand    = $get('brand');
            $priceStr = $get('price');

            if (!$name || !$brand) {
                throw new \RuntimeException("Missing model or brand for SKU: {$sku}");
            }
            if (!is_numeric($priceStr) || (float) $priceStr < 0) {
                throw new \RuntimeException("Invalid price for SKU: {$sku}");
            }
            $price = number_format((float) $priceStr, 2, '.', '');

            $availNodes = $el->getElementsByTagNameNS($nsUri, 'availability');
            if ($availNodes->length === 0) {
                $availNodes = $el->getElementsByTagName('availability');
            }

            $rawStock    = 0;
            $preOrder    = 0;
            $srcAvailable = 0;

            foreach ($availNodes as $avail) {
                /** @var \DOMElement $avail */
                if ($avail->getAttribute('storeId') !== 'PP3') {
                    continue;
                }
                $rawStock     = (int) $avail->getAttribute('stockCount');
                $preOrder     = (int) $avail->getAttribute('preOrder');
                $srcAvailable = $avail->getAttribute('available') === 'yes' ? 1 : 0;
                break;
            }

            $state    = (!$srcAvailable || $rawStock === 0)
                ? 'out_of_stock'
                : ($preOrder > 0 ? 'preorder' : 'in_stock');
            $quantity = $state === 'in_stock' ? $rawStock : 0;

            $offers[$sku] = [
                'name'             => $name,
                'brand'            => $brand,
                'price'            => $price,
                'raw_stock'        => $rawStock,
                'preorder_days'    => $preOrder,
                'source_available' => $srcAvailable,
                'quantity'         => $quantity,
                'state'            => $state,
            ];
        }

        if (empty($offers)) {
            throw new \RuntimeException('Feed parsed to zero offers; refusing to apply');
        }

        return $offers;
    }

    /**
     * Atomically upsert products + stock_pp3 for feed SKUs, then zero-out absent SKUs.
     *
     * Three-phase design:
     *   Phase 1 — Reset present_export = 0 for ALL stock_pp3 rows (marks everyone as absent).
     *   Phase 2 — Upsert feed SKUs (sets present_export = 1, restoring their active status).
     *   Phase 3 — Rows still at present_export = 0 were NOT in this feed:
     *              mark them out_of_stock and clear products.available/quantity.
     *
     * All phases run in one transaction. Rollback on any failure leaves the DB unchanged.
     *
     * @return array{0: int, 1: int, 2: array{feed_count: int, absent_count: int,
     *                                         zeroed_count: int, restored_count: int}}
     */
    private function applyFeed(array $offers, string $sha256): array
    {
        $now      = now()->toDateTimeString();
        $updated  = 0;
        $inserted = 0;
        $stats    = [];

        DB::transaction(function () use ($offers, $sha256, $now, &$updated, &$inserted, &$stats) {

            // ── Snapshot: SKUs that were previously absent AND are back in this feed ──
            // Computed before Phase 1 resets present_export, so the reading is accurate.
            $restoredCount = DB::table('stock_pp3')
                ->where('present_export', 0)
                ->whereIn('sku', array_keys($offers))
                ->count();

            // ── Phase 1: Mark feed-managed stock_pp3 rows as absent ──────────────
            // Only resets rows whose product is source='feed' and not exclude_from_feed_sync.
            // Manual and protected products' present_export is intentionally left untouched.
            DB::table('stock_pp3')
                ->whereIn('sku', function ($q) {
                    $q->select('sku')->from('products')
                      ->where('source', 'feed')
                      ->where('exclude_from_feed_sync', 0);
                })
                ->update(['present_export' => 0]);

            // ── Phase 2: Upsert feed SKUs ─────────────────────────────────────────
            // Protected products (source='manual' or exclude_from_feed_sync=1) are skipped
            // entirely — neither their product row nor their stock_pp3 row is touched.
            $existingSkus = DB::table('products')->pluck('sku')->flip()->all();

            $protectedSkus = DB::table('products')
                ->where(function ($q) {
                    $q->where('source', 'manual')->orWhere('exclude_from_feed_sync', 1);
                })
                ->pluck('sku')
                ->flip()
                ->all();

            $productRows = [];
            $stockRows   = [];

            foreach ($offers as $sku => $o) {
                if (isset($protectedSkus[$sku])) {
                    continue;
                }
                $isNew = !isset($existingSkus[$sku]);
                $isNew ? $inserted++ : $updated++;

                $productRows[] = [
                    'sku'                  => $sku,
                    'source_offer_id'      => $sku,
                    'name'                 => $o['name'],
                    'brand'                => $o['brand'],
                    'price_kzt'            => $o['price'],
                    'quantity'             => $o['quantity'],
                    'available'            => (int) ($o['state'] === 'in_stock'),
                    // Columns below only matter for new inserts; update list excludes them
                    'source_category_id'   => '__export_no_category__',
                    'source_category_path' => '',
                    'source_description'   => '',
                    'source_photos_json'   => '[]',
                    'present'              => 1,
                    'last_imported_at'     => $now,
                ];

                $stockRows[] = [
                    'sku'              => $sku,
                    'export_sku'       => $sku,
                    'match_method'     => 'exact',
                    'raw_stock'        => $o['raw_stock'],
                    'preorder_days'    => $o['preorder_days'],
                    'source_available' => $o['source_available'],
                    'quantity'         => $o['quantity'],
                    'state'            => $o['state'],
                    'present_export'   => 1,
                    'updated_at'       => $now,
                ];
            }

            foreach (array_chunk($productRows, self::CHUNK_SIZE) as $chunk) {
                DB::table('products')->upsert(
                    $chunk,
                    ['sku'],
                    ['name', 'brand', 'price_kzt', 'quantity', 'available', 'present', 'last_imported_at']
                );
            }

            foreach (array_chunk($stockRows, self::CHUNK_SIZE) as $chunk) {
                DB::table('stock_pp3')->upsert(
                    $chunk,
                    ['sku'],
                    ['export_sku', 'match_method', 'raw_stock', 'preorder_days',
                     'source_available', 'quantity', 'state', 'present_export', 'updated_at']
                );
            }

            // ── Phase 3: Zero out SKUs absent from this feed ──────────────────────
            // present_export = 0 after Phase 2 means the SKU was NOT in this feed.

            $absentCount = DB::table('stock_pp3')->where('present_export', 0)->count();

            $zeroedCount = DB::table('stock_pp3')
                ->where('present_export', 0)
                ->where('state', '!=', 'out_of_stock')
                ->count();

            if ($zeroedCount > 0) {
                // Phase 3: Zero out absent SKUs, excluding protected/manual products.
                DB::table('stock_pp3')
                    ->where('present_export', 0)
                    ->where('state', '!=', 'out_of_stock')
                    ->whereNotIn('sku', function ($q) {
                        $q->select('sku')->from('products')
                          ->where(function ($q2) {
                              $q2->where('source', 'manual')->orWhere('exclude_from_feed_sync', 1);
                          });
                    })
                    ->update([
                        'state'            => 'out_of_stock',
                        'quantity'         => 0,
                        'raw_stock'        => 0,
                        'source_available' => 0,
                        'updated_at'       => $now,
                    ]);

                // Sync products.available / quantity for the newly zeroed SKUs.
                // Uses a subquery for SQLite + MySQL compatibility (no JOIN-UPDATE).
                // Excludes protected/manual products from zeroing.
                $absentSkusSub = DB::table('stock_pp3')
                    ->where('present_export', 0)
                    ->whereNotIn('sku', function ($q) {
                        $q->select('sku')->from('products')
                          ->where(function ($q2) {
                              $q2->where('source', 'manual')->orWhere('exclude_from_feed_sync', 1);
                          });
                    })
                    ->select('sku');

                DB::table('products')
                    ->whereIn('sku', $absentSkusSub)
                    ->where(function ($q) {
                        $q->where('available', 1)->orWhere('quantity', '>', 0);
                    })
                    ->update([
                        'available'        => 0,
                        'quantity'         => 0,
                        'last_imported_at' => $now,
                    ]);
            }

            $stats = [
                'feed_count'     => count($offers),
                'absent_count'   => $absentCount,
                'zeroed_count'   => $zeroedCount,
                'restored_count' => $restoredCount,
            ];

            // ── Log run ──────────────────────────────────────────────────────────
            DB::table('catalog_sync_runs')->insert([
                'time'        => $now,
                'sha256'      => $sha256,
                'offer_count' => count($offers),
                'report_json' => json_encode([
                    'result'          => 'success',
                    'updated'         => $updated,
                    'inserted'        => $inserted,
                    'feed_count'      => count($offers),
                    'absent_count'    => $absentCount,
                    'zeroed_count'    => $zeroedCount,
                    'restored_count'  => $restoredCount,
                    'time'            => $now,
                    'mode'            => 'import',
                ], JSON_UNESCAPED_UNICODE),
            ]);
        });

        return [$updated, $inserted, $stats];
    }

    private function logRun(string $result, int $offerCount, string $detail = '', string $sha256 = ''): void
    {
        try {
            DB::table('catalog_sync_runs')->insert([
                'time'        => now()->toDateTimeString(),
                'sha256'      => $sha256,
                'offer_count' => $offerCount,
                'report_json' => json_encode([
                    'result' => $result,
                    'detail' => $detail,
                    'mode'   => 'import',
                    'time'   => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // Never throw from logging
        }
    }
}
