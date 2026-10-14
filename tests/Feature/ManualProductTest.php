<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for Phase C — manual products and XML-feed import protection.
 * Covers: storefront visibility, Filament panel, all 3 import-protection phases.
 */
class ManualProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('netbazar.catalog_baseline_offers', 1);

        DB::table('source_categories')->insertOrIgnore([
            ['id' => '__manual__',             'name' => 'Ручной товар',        'path' => '__manual__',             'present' => 0],
            ['id' => '__export_no_category__', 'name' => 'Без категории (фид)', 'path' => '__export_no_category__', 'present' => 0],
        ]);
    }

    // ─── Seed helpers ─────────────────────────────────────────────────────────

    private function seedManualProduct(string $sku, string $name = 'Ручной товар', array $overrides = []): void
    {
        DB::table('products')->insertOrIgnore(array_merge([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => $name,
            'brand'                => 'TestBrand',
            'price_kzt'            => '9990.00',
            'quantity'             => 5,
            'available'            => 1,
            'source_category_id'   => '__manual__',
            'source_category_path' => '',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'url_slug'             => strtolower(str_replace(' ', '-', $sku)),
            'source'               => 'manual',
            'exclude_from_feed_sync' => 1,
            'last_imported_at'     => now(),
        ], $overrides));

        DB::table('stock_pp3')->insertOrIgnore([
            'sku'              => $sku,
            'export_sku'       => $sku,
            'match_method'     => 'manual',
            'raw_stock'        => $overrides['quantity'] ?? 5,
            'preorder_days'    => 0,
            'source_available' => 1,
            'quantity'         => $overrides['quantity'] ?? 5,
            'state'            => 'in_stock',
            'present_export'   => 1,
            'updated_at'       => now(),
        ]);
    }

    private function seedFeedProduct(string $sku, string $name = 'Фидовый товар', array $overrides = []): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'feed-cat', 'name' => 'Feed Category', 'path' => 'Feed', 'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore(array_merge([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => $name,
            'brand'                => 'FeedBrand',
            'price_kzt'            => '5000.00',
            'quantity'             => 10,
            'available'            => 1,
            'source_category_id'   => 'feed-cat',
            'source_category_path' => 'Feed',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'source'               => 'feed',
            'exclude_from_feed_sync' => 0,
            'last_imported_at'     => now(),
        ], $overrides));

        DB::table('stock_pp3')->insertOrIgnore([
            'sku'              => $sku,
            'export_sku'       => $sku,
            'match_method'     => 'exact',
            'raw_stock'        => $overrides['quantity'] ?? 10,
            'preorder_days'    => 0,
            'source_available' => 1,
            'quantity'         => $overrides['quantity'] ?? 10,
            'state'            => 'in_stock',
            'present_export'   => 1,
            'updated_at'       => now(),
        ]);
    }

    private function buildXml(array $skus): string
    {
        $offers = '';
        foreach ($skus as $sku => $d) {
            $name     = htmlspecialchars($d['name']  ?? "Product {$sku}", ENT_XML1);
            $brand    = htmlspecialchars($d['brand'] ?? 'Brand',           ENT_XML1);
            $price    = number_format((float) ($d['price'] ?? 1000), 2, '.', '');
            $stock    = (int) ($d['stockCount'] ?? 0);
            $preOrder = (int) ($d['preOrder']   ?? 0);
            $avail    = ($d['available'] ?? true) ? 'yes' : 'no';
            $offers  .= <<<XML

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

    private function runImport(string $xml): int
    {
        $path = tempnam(sys_get_temp_dir(), 'manprod_test_') . '.xml';
        file_put_contents($path, $xml);
        try {
            return $this->artisan('netbazar:catalog-import', ['--file' => $path])->run();
        } finally {
            @unlink($path);
        }
    }

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // ─── 1. Manual product is visible at /product/{url_slug} ─────────────────

    public function test_manual_product_visible_on_product_page(): void
    {
        $this->seedManualProduct('MANUAL-001', 'Ручное кресло');

        $this->get('/product/manual-001')
            ->assertStatus(200)
            ->assertSee('Ручное кресло');
    }

    // ─── 2. Manual product appears in catalog search ──────────────────────────

    public function test_manual_product_visible_in_catalog(): void
    {
        $this->seedManualProduct('MANUAL-002', 'Ручной диван');

        $this->get('/catalog?q=MANUAL-002')
            ->assertStatus(200)
            ->assertSee('Ручной диван');
    }

    // ─── 3. Manual product appears in manual category ─────────────────────────

    public function test_manual_product_visible_in_manual_category(): void
    {
        $this->seedManualProduct('MANUAL-003', 'Кресло в категории');

        $catId = DB::table('manual_categories')->insertGetId([
            'name'       => 'Кресла',
            'slug'       => 'kresla-test',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_manual_categories')->insertOrIgnore([
            'sku' => 'MANUAL-003', 'manual_category_id' => $catId, 'sort_order' => 0,
        ]);

        $this->get('/category/kresla-test')
            ->assertStatus(200)
            ->assertSee('Кресло в категории');
    }

    // ─── 4. Phase 1: manual product's stock present_export NOT reset ──────────

    public function test_import_phase1_does_not_reset_manual_product_present_export(): void
    {
        $this->seedManualProduct('MANUAL-P1', 'Защищённый товар');

        // Feed contains only a filler — manual product is absent from feed
        $xml = $this->buildXml(['FILLER-P1' => ['stockCount' => 1]]);
        $this->runImport($xml);

        $stock = DB::table('stock_pp3')->where('sku', 'MANUAL-P1')->first();
        $this->assertNotNull($stock);
        $this->assertEquals(1, $stock->present_export, 'Phase 1 must not reset present_export for manual products');
    }

    // ─── 5. Phase 2: manual product NOT overwritten when SKU appears in feed ──

    public function test_import_phase2_does_not_overwrite_manual_product(): void
    {
        $this->seedManualProduct('MANUAL-P2', 'Мой ручной товар');
        DB::table('products')->where('sku', 'MANUAL-P2')->update(['price_kzt' => '9990.00', 'name' => 'Мой ручной товар']);

        // Feed contains the manual SKU with different data
        $xml = $this->buildXml([
            'MANUAL-P2' => ['name' => 'Перезаписанное имя', 'price' => 1, 'stockCount' => 0],
            'FILLER-P2' => ['stockCount' => 1],
        ]);
        $this->runImport($xml);

        $p = DB::table('products')->where('sku', 'MANUAL-P2')->first();
        $this->assertEquals('Мой ручной товар', $p->name, 'Phase 2 must not overwrite manual product name');
        $this->assertEquals('9990.00', $p->price_kzt, 'Phase 2 must not overwrite manual product price');
    }

    // ─── 6. Phase 3: manual product NOT zeroed when absent from feed ──────────

    public function test_import_phase3_does_not_zero_manual_product(): void
    {
        $this->seedManualProduct('MANUAL-P3', 'Товар вне фида');

        // Feed does NOT contain MANUAL-P3
        $xml = $this->buildXml(['FILLER-P3' => ['stockCount' => 1]]);
        $this->runImport($xml);

        $p = DB::table('products')->where('sku', 'MANUAL-P3')->first();
        $this->assertEquals(5, $p->quantity, 'Phase 3 must not zero manual product quantity');
        $this->assertEquals(1, $p->available, 'Phase 3 must not zero manual product availability');

        $s = DB::table('stock_pp3')->where('sku', 'MANUAL-P3')->first();
        $this->assertEquals('in_stock', $s->state, 'Phase 3 must not zero manual product stock state');
    }

    // ─── 7. Phase 1: protected feed SKU present_export NOT reset ─────────────

    public function test_import_phase1_does_not_reset_protected_feed_sku(): void
    {
        $this->seedFeedProduct('FEED-PROT', 'Защищённый фид', ['exclude_from_feed_sync' => 1]);
        DB::table('stock_pp3')->where('sku', 'FEED-PROT')->update(['present_export' => 1]);

        // Feed does NOT contain FEED-PROT
        $xml = $this->buildXml(['FILLER-7' => ['stockCount' => 1]]);
        $this->runImport($xml);

        $stock = DB::table('stock_pp3')->where('sku', 'FEED-PROT')->first();
        $this->assertEquals(1, $stock->present_export, 'Phase 1 must not reset present_export for exclude_from_feed_sync=1 products');
    }

    // ─── 8. Phase 2: protected feed SKU NOT overwritten ──────────────────────

    public function test_import_phase2_does_not_overwrite_protected_feed_sku(): void
    {
        $this->seedFeedProduct('FEED-PROT2', 'Мой фид товар', ['exclude_from_feed_sync' => 1, 'price_kzt' => '8888.00']);

        $xml = $this->buildXml([
            'FEED-PROT2' => ['name' => 'XML-перезапись', 'price' => 1, 'stockCount' => 0],
            'FILLER-8'   => ['stockCount' => 1],
        ]);
        $this->runImport($xml);

        $p = DB::table('products')->where('sku', 'FEED-PROT2')->first();
        $this->assertEquals('Мой фид товар', $p->name, 'exclude_from_feed_sync must protect product name from Phase 2');
        $this->assertEquals('8888.00', $p->price_kzt, 'exclude_from_feed_sync must protect product price from Phase 2');
    }

    // ─── 9. Phase 3: protected feed SKU NOT zeroed when absent from feed ──────

    public function test_import_phase3_does_not_zero_protected_feed_sku(): void
    {
        $this->seedFeedProduct('FEED-PROT3', 'Защита от нуля', ['exclude_from_feed_sync' => 1]);

        $xml = $this->buildXml(['FILLER-9' => ['stockCount' => 1]]);
        $this->runImport($xml);

        $p = DB::table('products')->where('sku', 'FEED-PROT3')->first();
        $this->assertEquals(10, $p->quantity, 'Phase 3 must not zero protected feed product quantity');

        $s = DB::table('stock_pp3')->where('sku', 'FEED-PROT3')->first();
        $this->assertEquals('in_stock', $s->state, 'Phase 3 must not zero protected feed product stock state');
    }

    // ─── 10. Unprotected feed SKU IS updated normally (regression check) ──────

    public function test_import_updates_normal_feed_sku_correctly(): void
    {
        $this->seedFeedProduct('FEED-NORMAL', 'Обычный товар', ['price_kzt' => '1000.00']);

        $xml = $this->buildXml([
            'FEED-NORMAL' => ['name' => 'Обновлённое имя', 'price' => 2500, 'stockCount' => 7],
        ]);
        $this->runImport($xml);

        $p = DB::table('products')->where('sku', 'FEED-NORMAL')->first();
        $this->assertEquals('Обновлённое имя', $p->name, 'Normal feed product name must be updated by Phase 2');
        $this->assertEquals('2500.00', $p->price_kzt, 'Normal feed product price must be updated by Phase 2');
        $this->assertEquals(7, $p->quantity);
    }

    // ─── 11. Feed product absent from XML gets zeroed (regression check) ──────

    public function test_import_zeros_absent_feed_sku(): void
    {
        $this->seedFeedProduct('FEED-ABSENT', 'Исчезнувший товар');

        $xml = $this->buildXml(['FILLER-11' => ['stockCount' => 1]]);
        $this->runImport($xml);

        $s = DB::table('stock_pp3')->where('sku', 'FEED-ABSENT')->first();
        $this->assertEquals('out_of_stock', $s->state, 'Absent feed product must be zeroed in Phase 3');
        $this->assertEquals(0, $s->quantity);
    }

    // ─── 12. editorial_photos_json used as highest priority photo ─────────────

    public function test_editorial_photos_take_priority_on_product_page(): void
    {
        $this->seedManualProduct('MANUAL-PHOTO', 'Фото товар');
        DB::table('products')->where('sku', 'MANUAL-PHOTO')->update([
            'editorial_photos_json' => json_encode(['/storage/product-photos/test.jpg']),
        ]);

        $response = $this->get('/product/manual-photo');
        $response->assertStatus(200);
        $response->assertSee('/storage/product-photos/test.jpg');
    }

    // ─── 13. Filament: product list page loads ────────────────────────────────

    public function test_filament_product_list_loads(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/admin/products')
            ->assertStatus(200);
    }

    // ─── 14. Filament: create product page loads ──────────────────────────────

    public function test_filament_create_product_page_loads(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/admin/products/create')
            ->assertStatus(200);
    }

    // ─── 15. Filament: edit manual product page loads ─────────────────────────

    public function test_filament_edit_manual_product_page_loads(): void
    {
        $this->seedManualProduct('MANUAL-EDIT', 'Редактируемый');

        $this->actingAs($this->adminUser())
            ->get('/admin/products/MANUAL-EDIT/edit')
            ->assertStatus(200);
    }

    // ─── 16. Manual product with present=1 appears in sitemap ─────────────────

    public function test_manual_product_appears_in_sitemap(): void
    {
        $this->seedManualProduct('MANUAL-SITEMAP', 'Карта сайта товар');

        $this->get(route('sitemap.products', ['page' => 1]))
            ->assertStatus(200)
            ->assertSee('manual-sitemap');
    }
}
