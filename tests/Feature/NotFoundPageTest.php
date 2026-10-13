<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the branded 404 error page.
 *
 * Covers:
 *   - Unknown URL → HTTP 404 with branded page
 *   - Branded page contains required text and links
 *   - meta robots noindex,follow is present
 *   - Satu legacy redirects still return 301 (not affected by 404 change)
 *   - Known product and catalog pages still return 200
 */
class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedProduct(string $sku, ?string $urlSlug = null): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-404-test', 'name' => 'Test', 'path' => 'Test', 'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => "Product $sku",
            'brand'                => 'TestBrand',
            'price_kzt'            => '1000.00',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-404-test',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'url_slug'             => $urlSlug,
            'last_imported_at'     => now(),
        ]);
    }

    // ─── 404 page: HTTP status ────────────────────────────────────────────────

    public function test_unknown_url_returns_http_404(): void
    {
        $this->get('/this-page-absolutely-does-not-exist-xyz123')
            ->assertNotFound();
    }

    public function test_deep_unknown_path_returns_http_404(): void
    {
        $this->get('/shop/some/deep/nonexistent/path')
            ->assertNotFound();
    }

    // ─── 404 page: branded content ───────────────────────────────────────────

    public function test_404_page_contains_404_number(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('404');
    }

    public function test_404_page_contains_heading(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('Упс! Что-то пошло не так.');
    }

    public function test_404_page_contains_subtitle(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('Похоже, такой страницы больше нет или ссылка устарела.');
    }

    // ─── 404 page: navigation links ──────────────────────────────────────────

    public function test_404_page_has_catalog_link(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('href="/catalog"', false);
    }

    public function test_404_page_catalog_button_has_correct_text(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('Перейти в каталог');
    }

    public function test_404_page_has_home_link(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('href="/"', false);
    }

    // ─── 404 page: SEO ───────────────────────────────────────────────────────

    public function test_404_page_has_noindex_robots_meta(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('noindex', false);
    }

    public function test_404_page_title_contains_404(): void
    {
        $this->get('/nonexistent-url-abc987')
            ->assertNotFound()
            ->assertSee('<title>', false)
            ->assertSee('404', false);
    }

    // ─── Satu redirects unaffected ───────────────────────────────────────────

    public function test_known_satu_product_redirect_still_returns_301(): void
    {
        $this->seedProduct('TEST-SKU-001', 'test-sku-001-slug');
        DB::table('satu_product_redirects')->insertOrIgnore([
            'satu_id' => 123456789,
            'sku'     => 'TEST-SKU-001',
        ]);

        $this->get('/p123456789-some-product-name.html')
            ->assertStatus(301)
            ->assertRedirect('/product/test-sku-001-slug');
    }

    public function test_known_satu_category_redirect_still_returns_301(): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'test-cat-404',
            'parent_id' => null,
            'name'      => 'Тест',
            'path'      => 'Тест',
            'source_id' => 'test-cat-404',
            'url_slug'  => 'test-category',
        ]);
        DB::table('satu_category_redirects')->insertOrIgnore([
            'satu_id'    => 987654321,
            'kaspi_id'   => 'test-cat-404',
            'target'     => null,
            'confidence' => 'high',
        ]);

        $this->get('/g987654321-test-category-name')
            ->assertStatus(301)
            ->assertRedirect('/catalog/test-category');
    }

    // ─── Known pages still open ──────────────────────────────────────────────

    public function test_existing_product_page_returns_200(): void
    {
        $this->seedProduct('PROD-OPEN-TEST', 'prod-open-test-slug');

        $this->get('/product/prod-open-test-slug')
            ->assertOk();
    }

    public function test_catalog_index_returns_200(): void
    {
        $this->get('/catalog')
            ->assertOk();
    }

    public function test_home_page_returns_200(): void
    {
        $this->get('/')
            ->assertOk();
    }
}
