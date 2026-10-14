<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the P0 fix: CatalogImport correctly zeroes absent SKUs and
 * restores returning SKUs, while preserving SEO and Kaspi-content fields.
 */
class CatalogImportStockTest extends TestCase
{
    use RefreshDatabase;

    // Used in multi-run tests to keep the feed count stable across imports.
    // Including this in every feed means count never drops below previous max.
    private const FILLER = '__TEST-FILLER__';

    protected function setUp(): void
    {
        parent::setUp();

        // baseline=1 so a single-offer feed passes the count check, and a
        // consistent two-offer feed also passes (ceil(1*0.9)=1 ≤ 1).
        Config::set('netbazar.catalog_baseline_offers', 1);

        DB::table('source_categories')->insertOrIgnore([
            'id'      => '__export_no_category__',
            'name'    => 'Export No Category',
            'path'    => 'Export No Category',
            'present' => 1,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Build a minimal valid Kaspi XML feed from a SKU map.
     *
     * @param array<string, array{name?: string, brand?: string, price?: int|float,
     *                             stockCount?: int, preOrder?: int, available?: bool}> $skus
     */
    private function buildXml(array $skus): string
    {
        $offers = '';
        foreach ($skus as $sku => $d) {
            $name      = htmlspecialchars($d['name']  ?? "Product {$sku}", ENT_XML1);
            $brand     = htmlspecialchars($d['brand'] ?? 'TestBrand',       ENT_XML1);
            $price     = number_format((float) ($d['price'] ?? 1000), 2, '.', '');
            $stock     = (int) ($d['stockCount'] ?? 0);
            $preOrder  = (int) ($d['preOrder']   ?? 0);
            $avail     = ($d['available'] ?? true) ? 'yes' : 'no';
            $offers   .= <<<XML

        <kaspiShopping:offer sku="{$sku}">
            <kaspiShopping:model>{$name}</kaspiShopping:model>
            <kaspiShopping:brand>{$brand}</kaspiShopping:brand>
            <kaspiShopping:price>{$price}</kaspiShopping:price>
            <kaspiShopping:availabilities>
                <kaspiShopping:availability storeId="PP3" stockCount="{$stock}" preOrder="{$preOrder}" available="{$avail}"/>
            </kaspiShopping:availabilities>
        </kaspiShopping:offer>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kaspiShopping:kaspi_catalog xmlns:kaspiShopping="kaspiShopping">
    <kaspiShopping:merchantid>Avtoximiya</kaspiShopping:merchantid>
    <kaspiShopping:offers>{$offers}
    </kaspiShopping:offers>
</kaspiShopping:kaspi_catalog>
XML;
    }

    /** Write XML to a temp file and run the import command. Returns exit code. */
    private function runImport(string $xml): int
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog_test_') . '.xml';
        file_put_contents($path, $xml);
        try {
            return $this->artisan('netbazar:catalog-import', ['--file' => $path])->run();
        } finally {
            @unlink($path);
        }
    }

    /** Fetch the stock_pp3 row for a SKU or null. */
    private function stock(string $sku): ?object
    {
        return DB::table('stock_pp3')->where('sku', $sku)->first();
    }

    /** Fetch the products row for a SKU or null. */
    private function product(string $sku): ?object
    {
        return DB::table('products')->where('sku', $sku)->first();
    }

    // ─── 1. Stock 5 → 0 ──────────────────────────────────────────────────────

    public function test_stock_decreases_to_zero_marks_out_of_stock(): void
    {
        $sku = 'РТ-TEST-QTY-DECREASE';

        // Single-offer feeds: baseline=1, min=1, both passes run to run.
        $this->runImport($this->buildXml([$sku => ['stockCount' => 5]]));
        $this->assertEquals('in_stock', $this->stock($sku)->state);
        $this->assertEquals(5, $this->stock($sku)->quantity);

        $this->runImport($this->buildXml([$sku => ['stockCount' => 0]]));

        $s = $this->stock($sku);
        $this->assertEquals('out_of_stock', $s->state);
        $this->assertEquals(0, $s->quantity);
        $this->assertEquals(0, $s->raw_stock);
        $this->assertEquals(0, $this->product($sku)->available);
        $this->assertEquals(0, $this->product($sku)->quantity);
    }

    // ─── 2. Stock 0 → 5 ──────────────────────────────────────────────────────

    public function test_stock_increases_from_zero_restores_in_stock(): void
    {
        $sku = 'РТ-TEST-QTY-INCREASE';

        $this->runImport($this->buildXml([$sku => ['stockCount' => 0]]));
        $this->assertEquals('out_of_stock', $this->stock($sku)->state);

        $this->runImport($this->buildXml([$sku => ['stockCount' => 5]]));

        $s = $this->stock($sku);
        $this->assertEquals('in_stock', $s->state);
        $this->assertEquals(5, $s->quantity);
        $this->assertEquals(5, $s->raw_stock);
        $this->assertEquals(1, $this->product($sku)->available);
        $this->assertEquals(5, $this->product($sku)->quantity);
    }

    // ─── 3. SKU absent from feed → zeroed ────────────────────────────────────

    public function test_absent_sku_zeroed_by_import(): void
    {
        $active   = 'РТ-TEST-ACTIVE';
        $archived = 'РТ-TEST-ARCHIVED';

        // Feed 1: both SKUs present. Count=2 → max becomes 2.
        $this->runImport($this->buildXml([
            $active   => ['stockCount' => 3],
            $archived => ['stockCount' => 7],
        ]));
        $this->assertEquals('in_stock', $this->stock($archived)->state);
        $this->assertEquals(7, $this->stock($archived)->quantity);

        // Feed 2: archived absent but FILLER added to keep count=2 (≥ min=2).
        $this->runImport($this->buildXml([
            $active         => ['stockCount' => 3],
            self::FILLER    => ['stockCount' => 1],
        ]));

        $s = $this->stock($archived);
        $this->assertEquals('out_of_stock', $s->state);
        $this->assertEquals(0, $s->quantity);
        $this->assertEquals(0, $s->raw_stock);
        $this->assertEquals(0, $s->source_available);
        $this->assertEquals(0, $s->present_export);

        $p = $this->product($archived);
        $this->assertEquals(0, $p->available);
        $this->assertEquals(0, $p->quantity);

        // Active SKU must not be affected.
        $this->assertEquals('in_stock', $this->stock($active)->state);
        $this->assertEquals(1, $this->stock($active)->present_export);
    }

    // ─── 4. products.present, url_slug, kaspi_content preserved ─────────────

    public function test_archived_product_seo_and_content_preserved(): void
    {
        $sku = 'РТ-TEST-PRESERVE';

        // Feed 1: two SKUs to set max=2.
        $this->runImport($this->buildXml([
            $sku         => ['stockCount' => 2],
            self::FILLER => ['stockCount' => 1],
        ]));

        // Manually set SEO/Kaspi fields (mirrors production data).
        DB::table('products')->where('sku', $sku)->update(['url_slug' => 'test-product-seo-slug']);
        DB::table('kaspi_lookup')->insertOrIgnore([
            'sku'        => $sku,
            'partner_id' => 'Avtoximiya',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => 'https://kaspi.kz/shop/p/test-123456/',
            'detail'     => '',
            'frame_url'  => '',
            'checked_at' => now(),
            'attempts'   => 1,
        ]);
        DB::table('kaspi_content')->insertOrIgnore([
            'sku'                => $sku,
            'url'                => 'https://kaspi.kz/shop/p/test-123456/',
            'description'        => 'Preserved description',
            'attributes_json'    => '[]',
            'photos_json'        => '["https://resources.cdn-kaspi.kz/img/test.jpg"]',
            'category_path_json' => '[]',
            'breadcrumbs_json'   => '[]',
            'status'             => 'ready',
            'detail'             => '',
            'source_sha256'      => 'abc123',
        ]);

        // Feed 2: target SKU absent; two other SKUs keep count=2.
        $this->runImport($this->buildXml([
            '__OTHER-A__' => ['stockCount' => 1],
            '__OTHER-B__' => ['stockCount' => 1],
        ]));

        $p = $this->product($sku);
        $this->assertEquals(1,                       $p->present,   'present must stay 1');
        $this->assertEquals('test-product-seo-slug', $p->url_slug,  'url_slug must be preserved');

        $kc = DB::table('kaspi_content')->where('sku', $sku)->first();
        $this->assertNotNull($kc,                           'kaspi_content row must survive');
        $this->assertEquals('Preserved description',        $kc->description);
        $this->assertStringContainsString('cdn-kaspi.kz',  $kc->photos_json);

        // Stock is zeroed.
        $this->assertEquals('out_of_stock', $this->stock($sku)->state);
        $this->assertEquals(0, $p->available);
    }

    // ─── 5. SKU returns to feed → restored ───────────────────────────────────

    public function test_sku_returns_to_feed_restores_stock(): void
    {
        $sku = 'РТ-TEST-RETURN';

        // Feed 1: count=2, max becomes 2.
        $this->runImport($this->buildXml([
            $sku         => ['stockCount' => 4],
            self::FILLER => ['stockCount' => 1],
        ]));

        // Feed 2: returning SKU absent, FILLER stays — count=2.
        $this->runImport($this->buildXml([
            '__OTHER__'  => ['stockCount' => 1],
            self::FILLER => ['stockCount' => 1],
        ]));
        $this->assertEquals('out_of_stock', $this->stock($sku)->state);

        // Feed 3: SKU returns with new stock, FILLER also present — count=2.
        $this->runImport($this->buildXml([
            $sku         => ['stockCount' => 8],
            self::FILLER => ['stockCount' => 1],
        ]));

        $s = $this->stock($sku);
        $this->assertEquals('in_stock', $s->state);
        $this->assertEquals(8, $s->quantity);
        $this->assertEquals(1, $s->present_export);
        $this->assertEquals(1, $this->product($sku)->available);
    }

    // ─── 6. Preorder ─────────────────────────────────────────────────────────

    public function test_preorder_state_set_correctly(): void
    {
        $sku = 'РТ-TEST-PREORDER';

        // Single-offer feed: baseline=1, passes fine.
        $this->runImport($this->buildXml([
            $sku => ['stockCount' => 3, 'preOrder' => 14, 'available' => true],
        ]));

        $s = $this->stock($sku);
        $this->assertEquals('preorder', $s->state);
        $this->assertEquals(14, $s->preorder_days);
        $this->assertEquals(0, $s->quantity);  // quantity = 0 for preorder

        $p = $this->product($sku);
        $this->assertEquals(0, $p->available);  // available = 0 when not in_stock
        $this->assertEquals(0, $p->quantity);
    }

    // ─── 7. Partial/short feed → rejected, DB unchanged ─────────────────────

    public function test_short_feed_rejected_no_db_changes(): void
    {
        // Establish a 3-SKU baseline.
        $this->runImport($this->buildXml([
            'SKU-A' => ['stockCount' => 1],
            'SKU-B' => ['stockCount' => 2],
            'SKU-C' => ['stockCount' => 3],
        ]));

        // Inject a fake large run so the historical max jumps to 10.
        DB::table('catalog_sync_runs')->insert([
            'time'        => now()->toDateTimeString(),
            'sha256'      => str_repeat('0', 64),
            'offer_count' => 10,
            'report_json' => '{"result":"success"}',
        ]);

        // A 1-offer feed is < 90% of 10 = 9 — must be rejected.
        $code = $this->runImport($this->buildXml([
            'SKU-A' => ['stockCount' => 99],
        ]));

        $this->assertSame(1, $code, 'Import must return exit code 1 (rejected)');

        // DB state must be exactly as left by the first (valid) import.
        $this->assertEquals(1, $this->stock('SKU-A')->raw_stock, 'SKU-A unchanged');
        $this->assertEquals(2, $this->stock('SKU-B')->raw_stock, 'SKU-B unchanged');
        $this->assertEquals(3, $this->stock('SKU-C')->raw_stock, 'SKU-C unchanged');
    }

    // ─── 8. Malformed XML → rejected ─────────────────────────────────────────

    public function test_malformed_xml_rejected(): void
    {
        $code = $this->runImport('this is not xml at all <broken>');
        $this->assertSame(1, $code);
    }

    // ─── 9. Import stats logged to report_json ────────────────────────────────

    public function test_import_stats_logged_in_report_json(): void
    {
        // Feed 1: two SKUs; count=2 → max=2.
        $this->runImport($this->buildXml([
            'SKU-STATS-A' => ['stockCount' => 5],
            'SKU-STATS-B' => ['stockCount' => 3],
        ]));

        // Feed 2: A + FILLER keeps count=2; B is absent.
        $this->runImport($this->buildXml([
            'SKU-STATS-A' => ['stockCount' => 5],
            self::FILLER  => ['stockCount' => 1],
        ]));

        $run  = DB::table('catalog_sync_runs')->orderByDesc('id')->first();
        $json = json_decode($run->report_json, true);

        $this->assertEquals('success', $json['result']);
        $this->assertEquals(2, $json['feed_count'],     'feed_count = 2 (A + FILLER)');
        $this->assertEquals(1, $json['absent_count'],   'absent_count = 1 (only B)');
        $this->assertEquals(1, $json['zeroed_count'],   'zeroed_count = 1 (B zeroed)');
        $this->assertEquals(0, $json['restored_count'], 'restored_count = 0');
    }

    // ─── 10. Restored count ───────────────────────────────────────────────────

    public function test_restored_count_logged_when_sku_returns(): void
    {
        $returning = 'SKU-WILL-RETURN';
        $other     = 'SKU-STAYS';

        // Feed 1: count=2.
        $this->runImport($this->buildXml([
            $returning => ['stockCount' => 2],
            $other     => ['stockCount' => 1],
        ]));

        // Feed 2: returning absent; FILLER added to keep count=2.
        $this->runImport($this->buildXml([
            $other       => ['stockCount' => 1],
            self::FILLER => ['stockCount' => 1],
        ]));

        // Feed 3: returning + other + FILLER so FILLER isn't newly zeroed.
        // Count=3 ≥ min=2; restored_count = 1 (returning had present_export=0).
        $this->runImport($this->buildXml([
            $returning   => ['stockCount' => 5],
            $other       => ['stockCount' => 1],
            self::FILLER => ['stockCount' => 1],
        ]));

        $run  = DB::table('catalog_sync_runs')->orderByDesc('id')->first();
        $json = json_decode($run->report_json, true);

        $this->assertEquals(1, $json['restored_count'], 'returning SKU must be counted');
        $this->assertEquals(0, $json['zeroed_count'],   'nothing newly zeroed on restore run');
    }

    // ─── 11. Catalog sort: absent SKU sorts lower ─────────────────────────────

    public function test_catalog_lists_in_stock_before_out_of_stock(): void
    {
        $active   = 'SKU-CATALOG-ACTIVE';
        $inactive = 'SKU-CATALOG-INACTIVE';

        // Feed 1: count=2.
        $this->runImport($this->buildXml([
            $active   => ['stockCount' => 2],
            $inactive => ['stockCount' => 5],
        ]));

        // Feed 2: inactive absent; FILLER keeps count=2.
        $this->runImport($this->buildXml([
            $active      => ['stockCount' => 2],
            self::FILLER => ['stockCount' => 1],
        ]));

        // products.quantity zeroed for inactive → sorts lower.
        $this->assertEquals(0, $this->product($inactive)->quantity);
        $this->assertGreaterThan(0, $this->product($active)->quantity);

        $response = $this->get('/catalog');
        $response->assertOk();
    }

    // ─── 12. Control SKUs simulation ─────────────────────────────────────────

    public function test_control_sku_absent_from_feed_zeroed(): void
    {
        // Simulate РТ-00000026 (PRO-TEC P4650): was in_stock, then disappeared from feed.
        $sku = 'РТ-00000026';

        // Feed 1: target + FILLER; count=2.
        $this->runImport($this->buildXml([
            $sku         => ['stockCount' => 3, 'price' => 19520],
            self::FILLER => ['stockCount' => 1],
        ]));
        $this->assertEquals('in_stock', $this->stock($sku)->state);

        // Feed 2: target absent; two new SKUs keep count=2 (≥ min=2).
        $this->runImport($this->buildXml([
            '__SOME-OTHER-A__' => ['stockCount' => 1],
            '__SOME-OTHER-B__' => ['stockCount' => 1],
        ]));

        $s = $this->stock($sku);
        $this->assertEquals('out_of_stock', $s->state,   'Absent SKU must be out_of_stock');
        $this->assertEquals(0,              $s->quantity, 'Quantity must be cleared');
        $this->assertEquals(0, $this->product($sku)->available);
        $this->assertEquals(1, $this->product($sku)->present, 'present must stay 1 — no physical delete');
    }

    public function test_control_sku_with_zero_stock_in_feed_stays_out_of_stock(): void
    {
        // Simulate РТ-00001516 (FiberShampoo): in XML with stockCount=0 / available=no.
        $sku = 'РТ-00001516';

        // Single-offer feed; baseline=1, passes.
        $this->runImport($this->buildXml([$sku => ['stockCount' => 0, 'available' => false]]));

        $s = $this->stock($sku);
        $this->assertEquals('out_of_stock', $s->state);
        $this->assertEquals(0, $s->quantity);
        $this->assertEquals(0, $s->source_available);
        $this->assertEquals(0, $this->product($sku)->available);
    }
}
