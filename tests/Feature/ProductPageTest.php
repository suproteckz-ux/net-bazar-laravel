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
}
