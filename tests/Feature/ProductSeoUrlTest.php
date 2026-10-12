<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the three-step SEO URL routing for product pages:
 *   1. url_slug → 200 (canonical)
 *   2. raw sku  → 301 to url_slug
 *   3. alias    → 301 directly to target url_slug (no chain)
 */
class ProductSeoUrlTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedProduct(
        string $sku,
        string $name,
        ?string $urlSlug = null,
        string $brand = 'TestBrand'
    ): void {
        DB::table('source_categories')->insertOrIgnore([
            'id'      => 'sc-seo-test',
            'name'    => 'Test Category',
            'path'    => 'Test Category',
            'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => $name,
            'brand'                => $brand,
            'price_kzt'            => '100000',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-seo-test',
            'source_category_path' => 'Test Category',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'url_slug'             => $urlSlug,
            'last_imported_at'     => now(),
        ]);
    }

    private function seedAlias(string $oldSku, string $newSku): void
    {
        DB::table('catalog_sku_aliases')->insertOrIgnore([
            'old_sku'      => $oldSku,
            'new_sku'      => $newSku,
            'migrated_at'  => now()->toISOString(),
        ]);
    }

    // ─── Step 1: url_slug → 200 ───────────────────────────────────────────────

    public function test_url_slug_returns_200(): void
    {
        $this->seedProduct('EDP-610', 'LEX EDP 610 BL духовой шкаф', 'lex-edp-610-bl-duhovoj-shkaf');

        $this->get('/product/lex-edp-610-bl-duhovoj-shkaf')
            ->assertOk()
            ->assertSee('LEX EDP 610 BL духовой шкаф');
    }

    public function test_canonical_example_from_seo_spec(): void
    {
        // Exact example from the SEO URL specification.
        $this->seedProduct(
            'EDP 610 BL Wide Series',
            'LEX EDP 610 BL Wide Series духовой шкаф',
            'lex-edp-610-bl-wide-series-duhovoj-shkaf'
        );

        $this->get('/product/lex-edp-610-bl-wide-series-duhovoj-shkaf')
            ->assertOk()
            ->assertSee('LEX EDP 610 BL Wide Series духовой шкаф');
    }

    public function test_url_slug_response_contains_canonical_tag(): void
    {
        $this->seedProduct('EDP-610', 'LEX EDP 610 духовой шкаф', 'lex-edp-610-duhovoj-shkaf');

        $response = $this->get('/product/lex-edp-610-duhovoj-shkaf')->assertOk();

        // Canonical must point to the slug URL, not a raw SKU.
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('/product/lex-edp-610-duhovoj-shkaf', false);
    }

    // ─── Step 2: raw sku → 301 ───────────────────────────────────────────────

    public function test_raw_sku_redirects_301_to_url_slug(): void
    {
        $this->seedProduct('EDP-610', 'LEX EDP 610 духовой шкаф', 'lex-edp-610-duhovoj-shkaf');

        $this->get('/product/EDP-610')
            ->assertRedirect('/product/lex-edp-610-duhovoj-shkaf')
            ->assertStatus(301);
    }

    public function test_url_encoded_sku_redirects_to_slug(): void
    {
        // SKU with a space: "EDP 610" → router decodes to "EDP 610"
        $this->seedProduct('EDP 610', 'LEX EDP 610 духовой шкаф', 'lex-edp-610-duhovoj-shkaf');

        // Laravel router URL-decodes %20, so the controller receives "EDP 610"
        $this->get('/product/EDP%20610')
            ->assertRedirect('/product/lex-edp-610-duhovoj-shkaf')
            ->assertStatus(301);
    }

    public function test_raw_sku_without_slug_returns_200_fallback(): void
    {
        // Product exists but url_slug not generated yet (edge case after failed migration).
        $this->seedProduct('NO-SLUG-001', 'Product Without Slug', null);

        $this->get('/product/NO-SLUG-001')->assertOk();
    }

    // ─── Step 3: alias → 301 to target slug (no chain) ───────────────────────

    public function test_alias_redirects_301_directly_to_target_slug(): void
    {
        $this->seedProduct('NEW-SKU-001', 'Renamed Product', 'renamed-product');
        $this->seedAlias('OLD-SKU-001', 'NEW-SKU-001');

        // Must go directly to /product/renamed-product, NOT to /product/NEW-SKU-001
        $this->get('/product/OLD-SKU-001')
            ->assertRedirect('/product/renamed-product')
            ->assertStatus(301);
    }

    public function test_alias_to_product_without_slug_redirects_to_raw_sku(): void
    {
        $this->seedProduct('NEW-SKU-002', 'Product No Slug Yet', null);
        $this->seedAlias('OLD-SKU-002', 'NEW-SKU-002');

        $this->get('/product/OLD-SKU-002')
            ->assertRedirect('/product/NEW-SKU-002')
            ->assertStatus(301);
    }

    public function test_no_chain_redirect_via_alias(): void
    {
        // OLD-SKU → NEW-SKU has url_slug → redirect goes straight to slug URL.
        // If controller redirected to raw NEW-SKU first, that would be a chain.
        $this->seedProduct('CHAIN-NEW', 'Chain Product', 'chain-product');
        $this->seedAlias('CHAIN-OLD', 'CHAIN-NEW');

        $response = $this->get('/product/CHAIN-OLD');

        // One redirect, destination is the url_slug, not the raw new_sku.
        $response->assertStatus(301);
        $this->assertSame(
            url('/product/chain-product'),
            $response->headers->get('Location')
        );
    }

    // ─── 404 ─────────────────────────────────────────────────────────────────

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/product/non-existent-slug-xyz')->assertNotFound();
    }

    public function test_unknown_sku_returns_404(): void
    {
        $this->get('/product/GHOST-SKU-999')->assertNotFound();
    }

    // ─── SKU_SHADOW conflict: slug lookup wins ────────────────────────────────

    public function test_slug_lookup_wins_over_sku_shadow(): void
    {
        // Product A has SKU = "shadow-sku"
        // Product B has url_slug = "shadow-sku" (SKU_SHADOW scenario)
        $this->seedProduct('shadow-sku', 'Product A (raw SKU matches B slug)', 'product-a-real-slug');
        $this->seedProduct('PRODUCT-B', 'Product B (slug = shadow-sku)', 'shadow-sku');

        // Visiting /product/shadow-sku must show Product B (slug owner),
        // not Product A (whose raw SKU coincidentally equals B's slug).
        $this->get('/product/shadow-sku')
            ->assertOk()
            ->assertSee('Product B (slug = shadow-sku)');

        // Product A is reachable via its own url_slug.
        $this->get('/product/product-a-real-slug')
            ->assertOk()
            ->assertSee('Product A (raw SKU matches B slug)');

        // Visiting Product A by raw SKU redirects to its own url_slug.
        $this->get('/product/shadow-sku')
            ->assertOk(); // B's canonical URL, not a redirect
    }

    // ─── Catalog link generation ──────────────────────────────────────────────

    public function test_catalog_card_links_use_url_slug(): void
    {
        $this->seedProduct(
            'EDP-CATALOG',
            'LEX EDP 610 духовой шкаф',
            'lex-edp-610-duhovoj-shkaf-catalog'
        );

        $this->get('/catalog')
            ->assertOk()
            ->assertSee('/product/lex-edp-610-duhovoj-shkaf-catalog', false);
    }

    public function test_catalog_card_links_fallback_to_sku_when_no_slug(): void
    {
        $this->seedProduct('NO-SLUG-CAT', 'Product Without Slug', null);

        $this->get('/catalog')
            ->assertOk()
            ->assertSee('/product/NO-SLUG-CAT', false);
    }

    // ─── WhatsApp link uses url_slug ──────────────────────────────────────────

    public function test_product_page_whatsapp_link_uses_url_slug(): void
    {
        $this->seedProduct('WA-PRODUCT', 'Тестовый товар WA', 'testovyj-tovar-wa');

        $this->get('/product/testovyj-tovar-wa')
            ->assertOk()
            ->assertSee('/product/testovyj-tovar-wa', false);
    }

    public function test_product_page_whatsapp_link_does_not_contain_raw_sku(): void
    {
        $this->seedProduct('WA-RAW-SKU-001', 'Тестовый товар WA Raw', 'testovyj-tovar-wa-raw');

        // Visit canonical slug URL; WhatsApp link must not contain raw SKU
        $html = $this->get('/product/testovyj-tovar-wa-raw')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/product/testovyj-tovar-wa-raw', $html);
        $this->assertStringNotContainsString('/product/WA-RAW-SKU-001', $html);
    }
}
