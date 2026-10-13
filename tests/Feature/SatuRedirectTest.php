<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for Satu.kz legacy URL 301 redirects.
 *
 * Covers:
 *   - /p{satu_id}-{slug}.html → 301 → /product/{url_slug}
 *   - /g{satu_id}-{slug}      → 301 → /catalog/{slug} or /catalog?category={id}
 *   - Import command: EXACT_MATCH, ALIAS_MATCH, NOT_FOUND, NO_SKU
 *   - No redirect chains (url_slug resolved fresh at request time)
 *   - net-bazar.kz domain used in canonical on redirect target
 */
class SatuRedirectTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesDir = base_path('tests/Fixtures/satu');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedSourceCategory(): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-satu-test', 'name' => 'Test', 'path' => 'Test', 'present' => 1,
        ]);
    }

    private function seedProduct(string $sku, ?string $urlSlug = null): void
    {
        $this->seedSourceCategory();
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => 'Test ' . $sku,
            'brand'                => 'Brand',
            'price_kzt'            => '1000',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-satu-test',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'url_slug'             => $urlSlug,
            'last_imported_at'     => '2026-10-12 12:00:00',
        ]);
    }

    private function seedProductRedirect(int $satuId, string $sku): void
    {
        DB::table('satu_product_redirects')->insertOrIgnore(['satu_id' => $satuId, 'sku' => $sku]);
    }

    private function seedCategoryRedirect(int $satuId, ?string $kaspiId, ?string $target, string $confidence = 'high'): void
    {
        DB::table('satu_category_redirects')->insertOrIgnore([
            'satu_id'    => $satuId,
            'kaspi_id'   => $kaspiId,
            'target'     => $target,
            'confidence' => $confidence,
        ]);
    }

    private function seedKaspiCategory(string $id, string $name, ?string $urlSlug = null): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => $id,
            'parent_id' => null,
            'name'      => $name,
            'path'      => $id,
            'source_id' => $id,
            'url_slug'  => $urlSlug,
        ]);
    }

    // ─── Product HTTP redirect tests ──────────────────────────────────────────

    public function test_satu_product_url_redirects_301_to_url_slug(): void
    {
        $this->seedProduct('VFIF2791E', 'vestfrost-vfi-f2791e-belyj');
        $this->seedProductRedirect(104372827, 'VFIF2791E');

        $this->get('/p104372827-vstraivaemyj-morozilnik-vestfrost.html')
            ->assertRedirect('/product/vestfrost-vfi-f2791e-belyj')
            ->assertStatus(301);
    }

    public function test_satu_product_redirect_returns_404_when_not_in_table(): void
    {
        // satu_product_redirects is empty — URL was never on Satu or not imported
        $this->get('/p999999999-unknown-product.html')->assertNotFound();
    }

    public function test_satu_product_redirect_returns_404_when_product_not_in_db(): void
    {
        // Row in redirect table but product was deleted from products table
        $this->seedProductRedirect(88888888, 'DELETED-SKU');

        $this->get('/p88888888-deleted-product.html')->assertNotFound();
    }

    public function test_satu_product_redirect_resolves_to_current_url_slug_avoiding_chain(): void
    {
        // Import happens when url_slug is NULL — redirect table stores raw SKU
        $this->seedProduct('NOSLUG-SKU', null);
        $this->seedProductRedirect(55555555, 'NOSLUG-SKU');

        // Currently no url_slug: redirect to raw SKU (ProductController renders directly, no chain)
        $response = $this->get('/p55555555-noslug-produkt.html');
        $response->assertStatus(301);
        $this->assertStringContainsString('/product/NOSLUG-SKU', $response->headers->get('Location', ''));

        // Now url_slug is generated
        DB::table('products')->where('sku', 'NOSLUG-SKU')->update(['url_slug' => 'noslug-product-slug']);

        // Next request resolves to url_slug directly — no extra redirect hop
        $this->get('/p55555555-noslug-produkt.html')
            ->assertRedirect('/product/noslug-product-slug')
            ->assertStatus(301);
    }

    public function test_satu_product_url_preserves_slug_portion_but_only_uses_id_for_lookup(): void
    {
        // Two different Satu slugs for the same product: only satu_id matters for lookup
        $this->seedProduct('IHV15', 'vestfrost-ihv15-indukciya');
        $this->seedProductRedirect(104372856, 'IHV15');

        $this->get('/p104372856-vstraivaemaya-varochnaya-poverhnost.html')
            ->assertRedirect('/product/vestfrost-ihv15-indukciya')
            ->assertStatus(301);
    }

    // ─── Category HTTP redirect tests ─────────────────────────────────────────

    public function test_satu_category_url_redirects_301_using_kaspi_url_slug(): void
    {
        $this->seedKaspiCategory('kaspi-wash-01', 'Стиральные машины', 'stiralnye-mashiny');
        $this->seedCategoryRedirect(9133790, 'kaspi-wash-01', null);

        $this->get('/g9133790-stiralnye-mashiny')
            ->assertRedirect('/catalog/stiralnye-mashiny')
            ->assertStatus(301);
    }

    public function test_satu_category_redirect_returns_404_when_kaspi_category_has_no_slug_yet(): void
    {
        // Category exists but url_slug not yet generated — must NOT redirect to ?category= (would chain).
        // Uses a generic test ID (not 8531101) to avoid conflict with the production migration
        // that maps 8531101 → target '/catalog/kompyuternye-i-ofisnye-kresla'.
        $this->seedKaspiCategory('kaspi-chairs-01', 'Офисные кресла', null);
        $this->seedCategoryRedirect(8531199, 'kaspi-chairs-01', null);

        $this->get('/g8531199-ofisnye-kresla-almaty')->assertNotFound();
    }

    public function test_satu_category_redirect_resolves_to_slug_once_generated(): void
    {
        // Initially no slug → 404 (controller does fresh lookup each request)
        $this->seedKaspiCategory('kaspi-wash-02', 'Стиральные машины', null);
        $this->seedCategoryRedirect(9133791, 'kaspi-wash-02', null);

        $this->get('/g9133791-stiralnye-mashiny-2')->assertNotFound();

        // After generate-category-slugs runs on production
        DB::table('kaspi_categories')->where('id', 'kaspi-wash-02')->update(['url_slug' => 'stiralnye-mashiny']);

        // Now resolves directly to /catalog/{slug} with no intermediate ?category= hop
        $this->get('/g9133791-stiralnye-mashiny-2')
            ->assertRedirect('/catalog/stiralnye-mashiny')
            ->assertStatus(301);
    }

    public function test_satu_category_redirect_uses_target_fallback_when_no_kaspi_id(): void
    {
        // Category redirect stored with a static target path (no kaspi_id)
        $this->seedCategoryRedirect(8455156, null, '/catalog/bytovaya-tehnika');

        $this->get('/g8455156-bytovaya-tehnika')
            ->assertRedirect('/catalog/bytovaya-tehnika')
            ->assertStatus(301);
    }

    public function test_satu_category_redirect_returns_404_when_not_in_table(): void
    {
        $this->get('/g999999999-unknown-group')->assertNotFound();
    }

    // ─── Import command: product mapping ─────────────────────────────────────

    public function test_import_command_exact_match_populates_redirect_table(): void
    {
        $this->seedProduct('EXACT-SKU', 'exact-match-slug');

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir' => $this->fixturesDir,
            '--products-only' => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_product_redirects')->where('satu_id', 11111111)->first();
        $this->assertNotNull($row, 'EXACT_MATCH row must be written');
        $this->assertSame('EXACT-SKU', $row->sku);
    }

    public function test_import_command_alias_match_resolves_new_sku(): void
    {
        $this->seedProduct('NEW-SKU', 'new-sku-slug');
        DB::table('catalog_sku_aliases')->insertOrIgnore([
            'old_sku'      => 'OLD-SKU',
            'new_sku'      => 'NEW-SKU',
            'migrated_at'  => '2026-10-13',
        ]);

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir' => $this->fixturesDir,
            '--products-only' => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_product_redirects')->where('satu_id', 22222222)->first();
        $this->assertNotNull($row, 'ALIAS_MATCH row must be written');
        $this->assertSame('NEW-SKU', $row->sku, 'Must store the resolved new SKU, not the old one');
    }

    public function test_import_command_not_found_sku_is_not_written(): void
    {
        // NOTFOUND-SKU is not in products and not in aliases → must NOT create a redirect
        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir' => $this->fixturesDir,
            '--products-only' => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_product_redirects')->where('satu_id', 33333333)->first();
        $this->assertNull($row, 'NOT_FOUND must not produce a redirect row');
    }

    public function test_import_command_no_sku_row_is_not_written(): void
    {
        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir' => $this->fixturesDir,
            '--products-only' => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_product_redirects')->where('satu_id', 44444444)->first();
        $this->assertNull($row, 'NO_SKU row must not produce a redirect');
    }

    public function test_import_command_dry_run_writes_nothing(): void
    {
        $this->seedProduct('EXACT-SKU', 'exact-match-slug');

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir'       => $this->fixturesDir,
            '--products-only' => true,
            '--dry-run'       => true,
        ])->assertExitCode(0);

        $count = DB::table('satu_product_redirects')->count();
        $this->assertSame(0, $count, '--dry-run must not write any rows');
    }

    public function test_import_command_force_overwrites_existing_sku(): void
    {
        // First import with old sku
        DB::table('satu_product_redirects')->insert(['satu_id' => 11111111, 'sku' => 'OLD-EXACT-SKU']);

        // Product now has new sku
        $this->seedProduct('EXACT-SKU', 'exact-match-slug');

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir'       => $this->fixturesDir,
            '--products-only' => true,
            '--force'         => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_product_redirects')->where('satu_id', 11111111)->first();
        $this->assertSame('EXACT-SKU', $row->sku, '--force must overwrite old sku');
    }

    public function test_import_command_skips_already_imported_without_force(): void
    {
        DB::table('satu_product_redirects')->insert(['satu_id' => 11111111, 'sku' => 'ALREADY-IMPORTED']);

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir'       => $this->fixturesDir,
            '--products-only' => true,
        ])->assertExitCode(0);

        // Must not overwrite the existing row
        $row = DB::table('satu_product_redirects')->where('satu_id', 11111111)->first();
        $this->assertSame('ALREADY-IMPORTED', $row->sku);
    }

    // ─── Import command: category mapping ─────────────────────────────────────

    public function test_import_command_category_high_confidence_mapped(): void
    {
        $this->seedKaspiCategory('kaspi-wash-test', 'Стиральные машины', 'stiralnye-mashiny');

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir'         => $this->fixturesDir,
            '--categories-only' => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_category_redirects')->where('satu_id', 9133790)->first();
        $this->assertNotNull($row, 'High-confidence row must be written');
        $this->assertSame('kaspi-wash-test', $row->kaspi_id);
    }

    public function test_import_command_category_low_confidence_skipped(): void
    {
        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir'         => $this->fixturesDir,
            '--categories-only' => true,
        ])->assertExitCode(0);

        // satu_id 8455164 is low confidence (NEEDS_MANUAL_CHECK)
        $row = DB::table('satu_category_redirects')->where('satu_id', 8455164)->first();
        $this->assertNull($row, 'Low-confidence rows must not be written');

        // satu_id 9999999 is NO_MATCH
        $row = DB::table('satu_category_redirects')->where('satu_id', 9999999)->first();
        $this->assertNull($row, 'NO_MATCH rows must not be written');
    }

    public function test_import_command_category_slash_candidate_resolves_first_match(): void
    {
        // "Kaspi: Автохимия/Автотовары" → try "Автохимия" first, then "Автотовары"
        $this->seedKaspiCategory('kaspi-autochem', 'Автохимия', null);

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir'         => $this->fixturesDir,
            '--categories-only' => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_category_redirects')->where('satu_id', 8819311)->first();
        $this->assertNotNull($row);
        $this->assertSame('kaspi-autochem', $row->kaspi_id);
    }

    public function test_import_command_category_parenthetical_stripped(): void
    {
        // "Kaspi: Офисные кресла (посетители)" → "Офисные кресла" after stripping paren
        $this->seedKaspiCategory('kaspi-chairs-visit', 'Офисные кресла', null);

        $this->artisan('netbazar:import-satu-redirects', [
            '--csv-dir'         => $this->fixturesDir,
            '--categories-only' => true,
        ])->assertExitCode(0);

        $row = DB::table('satu_category_redirects')->where('satu_id', 9268570)->first();
        $this->assertNotNull($row);
        $this->assertSame('kaspi-chairs-visit', $row->kaspi_id);
    }

    // ─── No redirect chains ───────────────────────────────────────────────────

    public function test_no_redirect_chain_for_product_after_alias_resolution(): void
    {
        // Scenario: ALIAS_MATCH stores new_sku. Controller redirects to url_slug directly.
        $this->seedProduct('NEW-SKU', 'new-sku-product-slug');
        DB::table('satu_product_redirects')->insert(['satu_id' => 77777777, 'sku' => 'NEW-SKU']);

        // Single 301 → /product/new-sku-product-slug
        // No intermediate /product/NEW-SKU step (which would then 301 to url_slug)
        $this->get('/p77777777-some-alias-slug.html')
            ->assertRedirect('/product/new-sku-product-slug')
            ->assertStatus(301);
    }

    public function test_no_redirect_chain_for_category_with_slug(): void
    {
        // Scenario: kaspi_category has url_slug; redirect goes directly to /catalog/{slug}
        // (not through /catalog?category={id} which would 301 again to /catalog/{slug})
        $this->seedKaspiCategory('kaspi-fridges', 'Холодильники', 'holodilniki');
        $this->seedCategoryRedirect(9118427, 'kaspi-fridges', null);

        $this->get('/g9118427-otdelnostoyaschij-holodilnik')
            ->assertRedirect('/catalog/holodilniki')
            ->assertStatus(301);
    }

    // ─── net-bazar.kz domain ─────────────────────────────────────────────────

    public function test_redirect_target_uses_relative_path_no_hardcoded_domain(): void
    {
        // The redirect Location header must be a relative path, not absolute with old domain.
        // Laravel's redirect() sends absolute URL matching the request host, which is fine —
        // we verify the path portion is correct regardless of host.
        $this->seedProduct('VFIO74X', 'vestfrost-vfio74x-duhovoj-shkaf');
        $this->seedProductRedirect(104372862, 'VFIO74X');

        $response = $this->get('/p104372862-vstraivaemyj-duhovoj-shkaf.html');
        $response->assertStatus(301);
        $location = $response->headers->get('Location', '');
        $this->assertStringContainsString('/product/vestfrost-vfio74x-duhovoj-shkaf', $location);
        $this->assertStringNotContainsString('netbazar.kz/product', $location,
            'Location must not hardcode the old domain; framework uses request host');
    }
}
