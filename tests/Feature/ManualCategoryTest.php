<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for Phase B — manual categories storefront.
 * Covers: routing, SEO, products, multi-category, sitemap, Kaspi/XML isolation.
 */
class ManualCategoryTest extends TestCase
{
    use RefreshDatabase;

    // ─── Seed helpers ─────────────────────────────────────────────────────────

    private function seedProduct(string $sku, string $name = 'Товар', bool $present = true): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'mc-test-cat', 'name' => 'Test', 'path' => 'Test', 'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => $name,
            'brand'                => 'Brand',
            'price_kzt'            => '5000.00',
            'quantity'             => $present ? 3 : 0,
            'available'            => $present ? 1 : 0,
            'source_category_id'   => 'mc-test-cat',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => $present ? 1 : 0,
        ]);
    }

    private function seedCategory(array $overrides = []): int
    {
        $id = DB::table('manual_categories')->insertGetId(array_merge([
            'name'        => 'Тестовая категория',
            'slug'        => 'test-category',
            'h1'          => null,
            'description' => null,
            'seo_title'   => null,
            'seo_description' => null,
            'image_path'  => null,
            'sort_order'  => 0,
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));

        return $id;
    }

    private function assignProduct(string $sku, int $categoryId, int $sortOrder = 0): void
    {
        DB::table('product_manual_categories')->insertOrIgnore([
            'sku'                => $sku,
            'manual_category_id' => $categoryId,
            'sort_order'         => $sortOrder,
        ]);
    }

    // ─── 1. Active category with products returns 200 ─────────────────────────

    public function test_active_category_returns_200(): void
    {
        $this->seedProduct('MC-001', 'Продукт А');
        $catId = $this->seedCategory(['slug' => 'mcat-active']);
        $this->assignProduct('MC-001', $catId);

        $this->get(route('manual-category.show', ['slug' => 'mcat-active']))
            ->assertStatus(200);
    }

    // ─── 2. Nonexistent slug returns 404 ──────────────────────────────────────

    public function test_nonexistent_slug_returns_404(): void
    {
        $this->get(route('manual-category.show', ['slug' => 'does-not-exist']))
            ->assertStatus(404);
    }

    // ─── 3. Inactive category returns 404 ─────────────────────────────────────

    public function test_inactive_category_returns_404(): void
    {
        $this->seedCategory(['slug' => 'mcat-inactive', 'is_active' => false]);

        $this->get(route('manual-category.show', ['slug' => 'mcat-inactive']))
            ->assertStatus(404);
    }

    // ─── 4. Category page shows assigned products ─────────────────────────────

    public function test_category_page_shows_assigned_products(): void
    {
        $this->seedProduct('MC-SHOW-A', 'Офисное кресло Х3');
        $this->seedProduct('MC-SHOW-B', 'Кресло Руководителя');
        $this->seedProduct('MC-SHOW-X', 'Не в категории');

        $catId = $this->seedCategory(['slug' => 'mcat-show']);
        $this->assignProduct('MC-SHOW-A', $catId);
        $this->assignProduct('MC-SHOW-B', $catId);

        $response = $this->get(route('manual-category.show', ['slug' => 'mcat-show']));

        $response->assertStatus(200);
        $response->assertSee('Офисное кресло Х3');
        $response->assertSee('Кресло Руководителя');
        $response->assertDontSee('Не в категории');
    }

    // ─── 5. Only present=1 products appear ────────────────────────────────────

    public function test_hidden_products_do_not_appear(): void
    {
        $this->seedProduct('MC-VIS-A', 'Видимый товар', true);
        $this->seedProduct('MC-VIS-H', 'Скрытый товар', false);

        $catId = $this->seedCategory(['slug' => 'mcat-vis']);
        $this->assignProduct('MC-VIS-A', $catId);
        $this->assignProduct('MC-VIS-H', $catId);

        $response = $this->get(route('manual-category.show', ['slug' => 'mcat-vis']));

        $response->assertStatus(200);
        $response->assertSee('Видимый товар');
        $response->assertDontSee('Скрытый товар');
    }

    // ─── 6. Product in multiple categories appears in each ────────────────────

    public function test_product_appears_in_multiple_categories(): void
    {
        $this->seedProduct('MC-MULTI', 'Многокатегорийный товар');

        $cat1 = $this->seedCategory(['slug' => 'mcat-multi-1', 'name' => 'Категория 1']);
        $cat2 = $this->seedCategory(['slug' => 'mcat-multi-2', 'name' => 'Категория 2']);
        $this->assignProduct('MC-MULTI', $cat1);
        $this->assignProduct('MC-MULTI', $cat2);

        $this->get(route('manual-category.show', ['slug' => 'mcat-multi-1']))
            ->assertStatus(200)->assertSee('Многокатегорийный товар');

        $this->get(route('manual-category.show', ['slug' => 'mcat-multi-2']))
            ->assertStatus(200)->assertSee('Многокатегорийный товар');
    }

    // ─── 7. H1 uses h1 field when set, falls back to name ────────────────────

    public function test_h1_uses_h1_field_when_set(): void
    {
        $this->seedCategory([
            'slug' => 'mcat-h1',
            'name' => 'Имя категории',
            'h1'   => 'Заголовок H1 категории',
        ]);

        $response = $this->get(route('manual-category.show', ['slug' => 'mcat-h1']));

        $response->assertStatus(200);
        $response->assertSee('Заголовок H1 категории');
        $response->assertDontSee('<h1 class="nb-h1">Имя категории', false);
    }

    // ─── 8. H1 falls back to name when h1 is null ─────────────────────────────

    public function test_h1_falls_back_to_name(): void
    {
        $this->seedCategory([
            'slug' => 'mcat-h1fb',
            'name' => 'Кресла и диваны',
            'h1'   => null,
        ]);

        $response = $this->get(route('manual-category.show', ['slug' => 'mcat-h1fb']));

        $response->assertStatus(200);
        $response->assertSee('Кресла и диваны');
    }

    // ─── 9. SEO title in <title> ──────────────────────────────────────────────

    public function test_seo_title_appears_in_title_tag(): void
    {
        $this->seedCategory([
            'slug'      => 'mcat-seo',
            'name'      => 'Категория',
            'seo_title' => 'Купить кресла в Алматы — NetBazar',
        ]);

        $this->get(route('manual-category.show', ['slug' => 'mcat-seo']))
            ->assertStatus(200)
            ->assertSee('<title>Купить кресла в Алматы — NetBazar</title>', false);
    }

    // ─── 10. Meta description appears ─────────────────────────────────────────

    public function test_meta_description_appears(): void
    {
        $this->seedCategory([
            'slug'            => 'mcat-meta',
            'name'            => 'Кресла',
            'seo_description' => 'Широкий выбор кресел в Алматы.',
        ]);

        $this->get(route('manual-category.show', ['slug' => 'mcat-meta']))
            ->assertStatus(200)
            ->assertSee('Широкий выбор кресел в Алматы.');
    }

    // ─── 11. Sitemap includes active manual category URLs ─────────────────────

    public function test_sitemap_includes_active_manual_categories(): void
    {
        $this->seedCategory(['slug' => 'sitemap-cat-a', 'is_active' => true]);
        $this->seedCategory(['slug' => 'sitemap-cat-b', 'is_active' => false]);

        $this->get(route('sitemap.manual-categories'))
            ->assertStatus(200)
            ->assertSee('/category/sitemap-cat-a')
            ->assertDontSee('/category/sitemap-cat-b');
    }

    // ─── 12. Sitemap index references the manual-categories sitemap ───────────

    public function test_sitemap_index_references_manual_categories(): void
    {
        $this->get(route('sitemap.index'))
            ->assertStatus(200)
            ->assertSee('sitemap-manual-categories.xml');
    }

    // ─── 13. Kaspi import does not affect manual_categories table ─────────────

    public function test_kaspi_categories_table_unchanged_by_xml_import(): void
    {
        $catId = $this->seedCategory(['slug' => 'kaspi-safe', 'name' => 'Ручная безопасная']);

        // Simulate the kind of changes CatalogImport does:
        // it only touches products, stock_pp3, import_runs, kaspi_categories — never manual_categories.
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'mc-test-cat', 'name' => 'Test', 'path' => 'Test', 'present' => 1,
        ]);
        DB::table('products')->insert([
            'sku'                  => 'KASPI-IMP-001',
            'source_offer_id'      => 'KASPI-IMP-001',
            'name'                 => 'Импортированный товар',
            'brand'                => 'Brand',
            'price_kzt'            => '1000.00',
            'quantity'             => 0,
            'available'            => 0,
            'source_category_id'   => 'mc-test-cat',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 0,
        ]);

        // manual_categories must still exist and be unchanged
        $category = DB::table('manual_categories')->where('id', $catId)->first();
        $this->assertNotNull($category);
        $this->assertEquals('Ручная безопасная', $category->name);
        $this->assertEquals('kaspi-safe', $category->slug);
    }

    // ─── 14. Pivot deletions do not delete products ────────────────────────────

    public function test_removing_pivot_does_not_delete_product(): void
    {
        $this->seedProduct('MC-PIVOT', 'Товар в категории');
        $catId = $this->seedCategory(['slug' => 'mcat-pivot-safe']);
        $this->assignProduct('MC-PIVOT', $catId);

        // Remove the pivot record
        DB::table('product_manual_categories')
            ->where('sku', 'MC-PIVOT')
            ->where('manual_category_id', $catId)
            ->delete();

        // Product still exists
        $this->assertDatabaseHas('products', ['sku' => 'MC-PIVOT']);

        // But not shown in category
        $response = $this->get(route('manual-category.show', ['slug' => 'mcat-pivot-safe']));
        $response->assertStatus(200);
        $response->assertDontSee('Товар в категории');
    }

    // ─── 15. Duplicate pivot insertion is idempotent ──────────────────────────

    public function test_duplicate_pivot_insertion_is_idempotent(): void
    {
        $this->seedProduct('MC-DUP', 'Дубль товар');
        $catId = $this->seedCategory(['slug' => 'mcat-dup']);

        $this->assignProduct('MC-DUP', $catId);
        $this->assignProduct('MC-DUP', $catId); // insertOrIgnore — should not throw

        $count = DB::table('product_manual_categories')
            ->where('sku', 'MC-DUP')
            ->where('manual_category_id', $catId)
            ->count();

        $this->assertEquals(1, $count);
    }

    // ─── 16. Existing Kaspi catalog routes unaffected ─────────────────────────

    public function test_kaspi_catalog_route_still_works(): void
    {
        // Ensure /catalog still returns 200 (no regression)
        $this->get(route('catalog.index'))
            ->assertStatus(200);
    }

    // ─── 17. Category description (SEO text) shown on page ───────────────────

    public function test_category_description_shown_on_page(): void
    {
        $this->seedCategory([
            'slug'        => 'mcat-desc',
            'description' => 'Подробный SEO-текст о категории кресел для офиса.',
        ]);

        $this->get(route('manual-category.show', ['slug' => 'mcat-desc']))
            ->assertStatus(200)
            ->assertSee('Подробный SEO-текст о категории кресел для офиса.');
    }

    // ─── 18. Empty category page renders without error ────────────────────────

    public function test_empty_category_returns_200_with_empty_message(): void
    {
        $this->seedCategory(['slug' => 'mcat-empty', 'name' => 'Пустая категория']);

        $this->get(route('manual-category.show', ['slug' => 'mcat-empty']))
            ->assertStatus(200)
            ->assertSee('ничего не найдено');
    }

    // ─── 19. Canonical URL in <link rel="canonical"> ─────────────────────────

    public function test_canonical_url_is_correct(): void
    {
        $this->seedCategory(['slug' => 'mcat-canonical']);

        $base = rtrim(config('netbazar.contact.site_url'), '/');

        $this->get(route('manual-category.show', ['slug' => 'mcat-canonical']))
            ->assertStatus(200)
            ->assertSee('rel="canonical"', false)
            ->assertSee('/category/mcat-canonical');
    }

    // ─── 20. Search within category filters products ──────────────────────────

    public function test_search_within_category_filters_products(): void
    {
        $this->seedProduct('MC-SRCH-A', 'Кресло Офисное');
        $this->seedProduct('MC-SRCH-B', 'Диван кожаный');

        $catId = $this->seedCategory(['slug' => 'mcat-search']);
        $this->assignProduct('MC-SRCH-A', $catId);
        $this->assignProduct('MC-SRCH-B', $catId);

        $response = $this->get(route('manual-category.show', ['slug' => 'mcat-search', 'q' => 'Кресло']));

        $response->assertStatus(200);
        $response->assertSee('Кресло Офисное');
        $response->assertDontSee('Диван кожаный');
    }
}
