<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Flush in-memory array cache between tests so stale cached XML
        // from a previous test (different product count) is never returned.
        Cache::flush();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedProduct(string $sku, ?string $urlSlug, bool $present = true, ?string $importedAt = null): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id'      => 'sc-sitemap',
            'name'    => 'Test',
            'path'    => 'Test',
            'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => 'Product ' . $sku,
            'brand'                => 'Brand',
            'price_kzt'            => '1000',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-sitemap',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => $present ? 1 : 0,
            'url_slug'             => $urlSlug,
            'last_imported_at'     => $importedAt ?? '2026-09-17 14:00:00',
        ]);
    }

    private function parsedXml(string $url): \SimpleXMLElement
    {
        $body = $this->get($url)->assertOk()->getContent();
        $xml  = simplexml_load_string($body);
        $this->assertNotFalse($xml, "Invalid XML at {$url}");
        return $xml;
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_sitemap_index_returns_200_with_xml_content_type(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function test_sitemap_index_is_valid_xml(): void
    {
        $this->parsedXml('/sitemap.xml'); // asserts no parse error
    }

    public function test_sitemap_index_lists_pages_categories_and_products(): void
    {
        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('sitemap-pages.xml',      $body);
        $this->assertStringContainsString('sitemap-categories.xml', $body);
        $this->assertStringContainsString('sitemap-products-1.xml', $body);
    }

    public function test_sitemap_index_creates_second_products_file_for_large_catalog(): void
    {
        // 501 products → 2 product sitemap files
        for ($i = 1; $i <= 501; $i++) {
            $this->seedProduct('SKU-' . $i, 'slug-' . $i);
        }

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('sitemap-products-1.xml', $body);
        $this->assertStringContainsString('sitemap-products-2.xml', $body);
        $this->assertStringNotContainsString('sitemap-products-3.xml', $body);
    }

    // ─── Pages sitemap ────────────────────────────────────────────────────────

    public function test_pages_sitemap_is_valid_xml(): void
    {
        $this->parsedXml('/sitemap-pages.xml');
    }

    public function test_pages_sitemap_contains_home_page(): void
    {
        $body = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://netbazar.kz/', $body);
    }

    public function test_pages_sitemap_contains_all_static_pages(): void
    {
        $body = $this->get('/sitemap-pages.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/catalog',           $body);
        $this->assertStringContainsString('/about',             $body);
        $this->assertStringContainsString('/contacts',          $body);
        $this->assertStringContainsString('/delivery-payment',  $body);
    }

    public function test_pages_sitemap_excludes_cart_checkout_api(): void
    {
        $body = $this->get('/sitemap-pages.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('/cart',     $body);
        $this->assertStringNotContainsString('/checkout', $body);
        $this->assertStringNotContainsString('/api/',     $body);
    }

    // ─── Categories sitemap ───────────────────────────────────────────────────

    public function test_categories_sitemap_is_valid_xml(): void
    {
        $this->parsedXml('/sitemap-categories.xml');
    }

    public function test_categories_sitemap_contains_canonical_slugs(): void
    {
        $body = $this->get('/sitemap-categories.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/catalog/bytovaya-tehnika',  $body);
        $this->assertStringContainsString('/catalog/kresla',            $body);
        $this->assertStringContainsString('/catalog/kulery-dlya-vody',  $body);
        $this->assertStringContainsString('/catalog/himiya',            $body);
        $this->assertStringContainsString('/catalog/avtoaksessuary',    $body);
    }

    public function test_categories_sitemap_excludes_query_param_urls(): void
    {
        $body = $this->get('/sitemap-categories.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('?category=', $body);
    }

    // ─── Products sitemap ─────────────────────────────────────────────────────

    public function test_products_sitemap_is_valid_xml(): void
    {
        $this->seedProduct('P1', 'my-product-slug');
        $this->parsedXml('/sitemap-products-1.xml');
    }

    public function test_products_sitemap_uses_url_slug_not_raw_sku(): void
    {
        $this->seedProduct('RAW-SKU-001', 'my-canonical-slug');

        $body = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/product/my-canonical-slug', $body);
        $this->assertStringNotContainsString('/product/RAW-SKU-001',    $body);
    }

    public function test_products_without_url_slug_excluded(): void
    {
        $this->seedProduct('NO-SLUG', null);         // url_slug = null
        $this->seedProduct('HAS-SLUG', 'has-slug');  // url_slug set

        $body = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/product/has-slug', $body);
        $this->assertStringNotContainsString('/product/NO-SLUG', $body);
        $this->assertStringNotContainsString('null', $body);
    }

    public function test_inactive_products_excluded(): void
    {
        $this->seedProduct('INACTIVE', 'inactive-slug', present: false);
        $this->seedProduct('ACTIVE',   'active-slug',   present: true);

        $body = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/product/active-slug',   $body);
        $this->assertStringNotContainsString('/product/inactive-slug', $body);
    }

    public function test_products_have_lastmod_in_yyyy_mm_dd_format(): void
    {
        $this->seedProduct('DATED', 'dated-product', importedAt: '2026-08-15 09:30:00');

        $body = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();

        // Must have Y-m-d date, not datetime or current date
        $this->assertStringContainsString('<lastmod>2026-08-15</lastmod>', $body);
        $this->assertStringNotContainsString('09:30', $body); // no time component
    }

    public function test_empty_catalog_returns_valid_empty_urlset(): void
    {
        // No products in DB — should still return valid XML with empty urlset
        $xml = $this->parsedXml('/sitemap-products-1.xml');

        $this->assertSame('urlset', $xml->getName());
        $this->assertCount(0, $xml->url ?? []);
    }

    public function test_products_page_2_returns_404_when_only_one_chunk(): void
    {
        $this->seedProduct('P1', 'slug-one');

        $this->get('/sitemap-products-2.xml')->assertNotFound();
    }

    public function test_products_page_0_returns_404(): void
    {
        // Route constraint [1-9][0-9]* prevents page=0; fallback to 404
        $this->get('/sitemap-products-0.xml')->assertNotFound();
    }

    public function test_products_split_correctly_across_two_chunks(): void
    {
        // 501 products → 500 in chunk 1, 1 in chunk 2
        for ($i = 1; $i <= 501; $i++) {
            $this->seedProduct('SKU-' . str_pad($i, 4, '0', STR_PAD_LEFT), 'slug-' . $i);
        }
        Cache::flush(); // clear cached chunk count from seedProduct loop

        $xml1 = $this->parsedXml('/sitemap-products-1.xml');
        $xml2 = $this->parsedXml('/sitemap-products-2.xml');

        $this->assertCount(500, $xml1->url);
        $this->assertCount(1,   $xml2->url);
    }

    // ─── URL quality ─────────────────────────────────────────────────────────

    public function test_all_urls_are_absolute_https(): void
    {
        $this->seedProduct('P1', 'abs-slug-1');
        $this->seedProduct('P2', 'abs-slug-2');

        foreach (['/sitemap.xml', '/sitemap-pages.xml', '/sitemap-categories.xml', '/sitemap-products-1.xml'] as $path) {
            $body = $this->get($path)->assertOk()->getContent();
            // Every <loc> must start with https://
            preg_match_all('|<loc>(.*?)</loc>|', $body, $m);
            foreach ($m[1] as $loc) {
                $this->assertStringStartsWith('https://', $loc, "Non-HTTPS loc in {$path}: {$loc}");
            }
        }
    }

    public function test_no_duplicate_urls_in_products_sitemap(): void
    {
        $this->seedProduct('D1', 'dup-test-slug');

        $body = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();

        preg_match_all('|<loc>(.*?)</loc>|', $body, $m);
        $locs = $m[1];
        $this->assertSame(count($locs), count(array_unique($locs)), 'Duplicate <loc> entries found');
    }

    public function test_no_redirect_urls_in_sitemap(): void
    {
        // Raw SKU URL (would trigger 301 in ProductController) must NOT appear
        $this->seedProduct('RAW-SKU-999', 'canonical-slug-999');

        $body = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/product/canonical-slug-999', $body);
        $this->assertStringNotContainsString('/product/RAW-SKU-999',     $body);
    }

    // ─── Domain configuration ─────────────────────────────────────────────────

    public function test_urls_use_configured_site_url(): void
    {
        // Temporarily override site_url to simulate domain migration
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);
        Cache::flush(); // flush cached results that used old domain

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://net-bazar.kz/', $body);
        $this->assertStringNotContainsString('https://netbazar.kz', $body);
    }

    public function test_default_domain_is_netbazar_kz(): void
    {
        $body = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://netbazar.kz', $body);
    }

    // ─── Cache domain isolation ───────────────────────────────────────────────

    public function test_cache_keys_are_namespaced_per_domain(): void
    {
        // Prime cache under netbazar.kz
        config(['netbazar.contact.site_url' => 'https://netbazar.kz']);
        Cache::flush();
        $body1 = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://netbazar.kz', $body1);

        // Switch domain — no Cache::flush() — domain-namespaced key must produce a fresh response
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);
        $body2 = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://net-bazar.kz', $body2);
        $this->assertStringNotContainsString('https://netbazar.kz', $body2);
    }

    public function test_products_cache_keys_are_namespaced_per_domain(): void
    {
        $this->seedProduct('DOMAIN-P1', 'domain-slug-1');

        // Prime under netbazar.kz
        config(['netbazar.contact.site_url' => 'https://netbazar.kz']);
        $body1 = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://netbazar.kz/product/domain-slug-1', $body1);

        // Switch domain without flushing — must get fresh URL
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);
        $body2 = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://net-bazar.kz/product/domain-slug-1', $body2);
        $this->assertStringNotContainsString('https://netbazar.kz', $body2);
    }

    // ─── Dynamic robots.txt ───────────────────────────────────────────────────

    public function test_robots_txt_returns_200_with_text_content_type(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function test_robots_txt_contains_sitemap_directive(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: https://netbazar.kz/sitemap.xml', $body);
    }

    public function test_robots_txt_allows_all_crawlers(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Disallow:', $body);
    }

    public function test_robots_txt_sitemap_url_uses_configured_domain(): void
    {
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: https://net-bazar.kz/sitemap.xml', $body);
        $this->assertStringNotContainsString('https://netbazar.kz', $body);
    }

    // ─── Canonical example ────────────────────────────────────────────────────

    public function test_canonical_lex_edp_product_appears_in_sitemap(): void
    {
        $this->seedProduct(
            'EDP 610 BL Wide Series',
            'lex-edp-610-bl-wide-series-duhovoj-shkaf'
        );

        $body = $this->get('/sitemap-products-1.xml')->assertOk()->getContent();

        $this->assertStringContainsString(
            'https://netbazar.kz/product/lex-edp-610-bl-wide-series-duhovoj-shkaf',
            $body
        );
    }

    // ─── net-bazar.kz domain ─────────────────────────────────────────────────

    public function test_canonical_tag_uses_net_bazar_kz_when_configured(): void
    {
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);

        $body = $this->get('/catalog')->assertOk()->getContent();

        $this->assertStringContainsString(
            'rel="canonical" href="https://net-bazar.kz/catalog"',
            $body
        );
        $this->assertStringNotContainsString('canonical" href="https://netbazar.kz', $body);
    }

    public function test_sitemap_categories_uses_net_bazar_kz_when_configured(): void
    {
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);
        Cache::flush();

        $body = $this->get('/sitemap-categories.xml')->assertOk()->getContent();

        // All <loc> entries must use net-bazar.kz
        preg_match_all('|<loc>(.*?)</loc>|', $body, $m);
        $this->assertNotEmpty($m[1], 'Sitemap must contain at least one <loc>');
        foreach ($m[1] as $loc) {
            $this->assertStringStartsWith('https://net-bazar.kz/catalog/', $loc, "Non-net-bazar.kz loc: {$loc}");
        }
        $this->assertStringNotContainsString('netbazar.kz', $body);
    }

    public function test_robots_txt_uses_net_bazar_kz_sitemap_url(): void
    {
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Sitemap: https://net-bazar.kz/sitemap.xml', $body);
        $this->assertStringNotContainsString('netbazar.kz', $body);
    }

    public function test_contacts_page_shows_configured_domain(): void
    {
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);

        $body = $this->get('/contacts')->assertOk()->getContent();

        // Link href and visible text must both use net-bazar.kz
        $this->assertStringContainsString('href="https://net-bazar.kz"', $body);
        $this->assertStringContainsString('>net-bazar.kz<', $body);
    }
}
