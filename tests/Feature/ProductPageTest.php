<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedProduct(string $sku, string $sourceCategoryName = 'Source Category'): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-' . $sku, 'name' => $sourceCategoryName,
            'path' => $sourceCategoryName, 'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => "Product $sku",
            'brand'                => 'Brand',
            'price_kzt'            => '1000.00',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-' . $sku,
            'source_category_path' => $sourceCategoryName,
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'last_imported_at'     => now(),
        ]);
    }

    private function seedKaspiCategory(string $id, ?string $parentId, string $name, string $path): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => $id,
            'parent_id' => $parentId,
            'name'      => $name,
            'path'      => $path,
            'source_id' => $id,
        ]);
    }

    private function linkProductCategory(string $sku, string $categoryId): void
    {
        DB::table('product_kaspi_categories')->insertOrIgnore([
            'sku'         => $sku,
            'category_id' => $categoryId,
        ]);
    }

    private function seedKaspiLookup(string $sku, string $url): void
    {
        DB::table('kaspi_lookup')->insertOrIgnore([
            'sku'        => $sku,
            'partner_id' => 'netbazar',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => $url,
            'detail'     => '',
            'frame_url'  => '',
        ]);
    }

    private function seedStockPp3(string $sku, string $state, int $quantity = 1): void
    {
        DB::table('stock_pp3')->insertOrIgnore([
            'sku'              => $sku,
            'export_sku'       => $sku,
            'match_method'     => 'exact',
            'raw_stock'        => $quantity,
            'preorder_days'    => 0,
            'source_available' => $quantity > 0 ? 1 : 0,
            'quantity'         => $quantity,
            'state'            => $state,
            'present_export'   => $quantity > 0 ? 1 : 0,
            'updated_at'       => now()->toISOString(),
        ]);
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_product_page_shows_kaspi_category_breadcrumb(): void
    {
        $this->seedProduct('SKU-001', 'Встраиваемые духовки');
        $this->seedKaspiCategory('cat-coolers-01', null, 'Кулеры для воды', 'Кулеры для воды');
        $this->linkProductCategory('SKU-001', 'cat-coolers-01');

        $resp = $this->get(route('product.show', ['sku' => 'SKU-001']))->assertOk();

        $resp->assertSee('Кулеры для воды');
        $resp->assertDontSee('Встраиваемые духовки');
    }

    public function test_product_page_falls_back_to_source_category_without_kaspi_assignment(): void
    {
        $this->seedProduct('SKU-002', 'Встраиваемые духовки');
        // No product_kaspi_categories row

        $resp = $this->get(route('product.show', ['sku' => 'SKU-002']))->assertOk();

        $resp->assertSee('Встраиваемые духовки');
    }

    public function test_product_breadcrumbs_use_override_display_name(): void
    {
        $this->seedProduct('SKU-003', 'Old Source');
        $this->seedKaspiCategory('cat-override-01', null, 'Кулеры для воды', 'Кулеры для воды');
        $this->linkProductCategory('SKU-003', 'cat-override-01');

        DB::table('kaspi_category_overrides')->insert([
            'category_id'  => 'cat-override-01',
            'display_name' => 'Кулеры и диспенсеры',
        ]);

        $resp = $this->get(route('product.show', ['sku' => 'SKU-003']))->assertOk();

        // Override display_name must appear in breadcrumbs
        $resp->assertSee('Кулеры и диспенсеры');
        // Breadcrumb link must point to the category via its id
        $resp->assertSee(route('catalog.index', ['category' => 'cat-override-01']));
    }

    public function test_product_breadcrumbs_show_all_ancestor_levels(): void
    {
        $this->seedProduct('SKU-004', 'Old Source');
        $this->seedKaspiCategory('cat-root-01', null,        'Бытовая техника',          'Бытовая техника');
        $this->seedKaspiCategory('cat-leaf-01', 'cat-root-01', 'Кулеры для воды', 'Бытовая техника / Кулеры для воды');
        $this->linkProductCategory('SKU-004', 'cat-leaf-01');

        $resp = $this->get(route('product.show', ['sku' => 'SKU-004']))->assertOk();

        $resp->assertSee('Бытовая техника');
        $resp->assertSee('Кулеры для воды');
        $resp->assertSee(route('catalog.index', ['category' => 'cat-root-01']));
        $resp->assertSee(route('catalog.index', ['category' => 'cat-leaf-01']));
    }

    public function test_product_breadcrumbs_after_rebuild_exhaust(): void
    {
        // Simulates GS BLOC GS 900 WHITE after netbazar:rebuild-kaspi-categories
        $sku = 'GS BLOC GS 900 WHITE';
        $this->seedProduct($sku, 'Встраиваемые духовки');

        $leafId = 'b03186f32cc9f323b8ce1589'; // Python-computed ID for Вытяжки path
        $this->seedKaspiCategory('parent-exhaust-01',  null,               'Бытовая техника', 'Бытовая техника');
        $this->seedKaspiCategory('parent-exhaust-02',  'parent-exhaust-01', 'Крупная техника для дома', 'Бытовая техника / Крупная техника для дома');
        $this->seedKaspiCategory($leafId,              'parent-exhaust-02', 'Вытяжки', 'Бытовая техника / Крупная техника для дома / Вытяжки');
        $this->linkProductCategory($sku, $leafId);

        $resp = $this->get(route('product.show', ['sku' => $sku]))->assertOk();

        $resp->assertSee('Вытяжки');
        $resp->assertSee('Бытовая техника');
        $resp->assertDontSee('Встраиваемые духовки');
    }

    public function test_product_breadcrumbs_after_rebuild_cooler(): void
    {
        // Simulates 671143194 after netbazar:rebuild-kaspi-categories
        $sku = '671143194';
        $this->seedProduct($sku, 'Встраиваемые духовки');

        $leafId = '3b50c632c5fcf97505984a51'; // Python-computed ID for Кулеры для воды path
        $this->seedKaspiCategory('parent-cooler-01',  null,              'Бытовая техника', 'Бытовая техника');
        $this->seedKaspiCategory('parent-cooler-02',  'parent-cooler-01', 'Крупная техника для дома', 'Бытовая техника / Крупная техника для дома');
        $this->seedKaspiCategory($leafId,             'parent-cooler-02', 'Кулеры для воды', 'Бытовая техника / Крупная техника для дома / Кулеры для воды');
        $this->linkProductCategory($sku, $leafId);

        $resp = $this->get(route('product.show', ['sku' => $sku]))->assertOk();

        $resp->assertSee('Кулеры для воды');
        $resp->assertSee('Бытовая техника');
        $resp->assertDontSee('Встраиваемые духовки');
    }

    // ─── Kaspi button availability tests ─────────────────────────────────────

    public function test_kaspi_button_hidden_when_out_of_stock(): void
    {
        // Reproduces the production bug: product shows "Нет в наличии" but
        // Kaspi button was still visible because only kaspi_confirmed_url was checked.
        $sku = 'OOS-KASPI-01';
        $this->seedProduct($sku);
        DB::table('products')->where('sku', $sku)->update(['available' => 0]);
        $this->seedStockPp3($sku, 'out_of_stock', 0);
        $this->seedKaspiLookup($sku, 'https://kaspi.kz/shop/p/test-oos');

        $resp = $this->get(route('product.show', ['sku' => $sku]))->assertOk();

        $resp->assertDontSee('Купить на Kaspi');
        // "В корзину" button must be rendered but disabled
        $resp->assertSee('В корзину');
        $resp->assertSee('type="submit" disabled', false);
    }

    public function test_kaspi_button_shown_when_in_stock_with_url(): void
    {
        $sku = 'IN-STOCK-KASPI-01';
        $this->seedProduct($sku);
        $this->seedStockPp3($sku, 'in_stock', 5);
        $this->seedKaspiLookup($sku, 'https://kaspi.kz/shop/p/test-in-stock');

        $resp = $this->get(route('product.show', ['sku' => $sku]))->assertOk();

        $resp->assertSee('Купить на Kaspi');
        // "В корзину" button must be enabled (no disabled attribute)
        $resp->assertSee('В корзину');
        $resp->assertDontSee('type="submit" disabled', false);
    }

    public function test_kaspi_button_absent_when_no_url_even_if_in_stock(): void
    {
        $sku = 'NO-URL-KASPI-01';
        $this->seedProduct($sku);
        $this->seedStockPp3($sku, 'in_stock', 3);
        // No kaspi_lookup row — no confirmed URL

        $resp = $this->get(route('product.show', ['sku' => $sku]))->assertOk();

        $resp->assertDontSee('Купить на Kaspi');
        // Cart button still active (stock is positive)
        $resp->assertDontSee('type="submit" disabled', false);
    }

    public function test_kaspi_button_returns_when_stock_becomes_positive(): void
    {
        $sku = 'RECOVER-KASPI-01';
        $this->seedProduct($sku);
        DB::table('products')->where('sku', $sku)->update(['available' => 0]);
        $this->seedStockPp3($sku, 'out_of_stock', 0);
        $this->seedKaspiLookup($sku, 'https://kaspi.kz/shop/p/test-recover');

        // Initially out of stock — Kaspi button hidden
        $this->get(route('product.show', ['sku' => $sku]))->assertDontSee('Купить на Kaspi');

        // Stock recovers: available=1, stock state becomes in_stock
        DB::table('products')->where('sku', $sku)->update(['available' => 1]);
        DB::table('stock_pp3')->where('sku', $sku)->update(['state' => 'in_stock', 'quantity' => 2]);

        // Kaspi button must reappear
        $this->get(route('product.show', ['sku' => $sku]))->assertSee('Купить на Kaspi');
    }
}
