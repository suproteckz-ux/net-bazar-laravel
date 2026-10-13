<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Middleware: RedirectTechnicalDomain
 *
 * Verifies that:
 *   - netbazar.kz / www.netbazar.kz → 301 to net-bazar.kz (GET/HEAD only)
 *   - net-bazar.kz and unknown hosts are never redirected
 *   - POST/PATCH/DELETE are passed through unchanged
 *   - /up health endpoint is never redirected
 *   - Satu product/category paths are resolved to their canonical URL in one hop
 *   - Loop guard: no redirect when canonical host equals the technical host
 *
 * Note on host setting in tests:
 *   withServerVariables(['HTTP_HOST' => ...]) is overridden by Symfony's Request::create,
 *   which derives HTTP_HOST from the URI (Laravel prepends APP_URL=http://localhost).
 *   To set the request host, we pass the full URL: http://{host}{path}.
 */
class TechnicalDomainRedirectTest extends TestCase
{
    use RefreshDatabase;

    // Canonical host used in all redirect tests (must differ from tech domains).
    private const CANONICAL = 'https://net-bazar.kz';

    protected function setUp(): void
    {
        parent::setUp();
        // Override canonical so the loop guard doesn't suppress redirects.
        // (phpunit.xml sets NETBAZAR_SITE_URL=https://netbazar.kz by default,
        //  which would trigger the loop guard and prevent all redirects in tests.)
        config(['netbazar.contact.site_url' => self::CANONICAL]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Make a test request with a specific Host by using the full URL.
     * Symfony::Request::create() derives HTTP_HOST from the URI host component,
     * so this is the only reliable way to control the host in test requests.
     */
    private function fromHost(string $host, string $path, string $method = 'GET'): TestResponse
    {
        return $this->call($method, 'http://' . $host . $path);
    }

    // ── Basic redirect ────────────────────────────────────────────────────────

    public function test_netbazar_home_redirects_301_to_canonical(): void
    {
        $this->fromHost('netbazar.kz', '/')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/');
    }

    public function test_www_netbazar_kz_redirects_301(): void
    {
        $this->fromHost('www.netbazar.kz', '/')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/');
    }

    // ── Path and query string preservation ───────────────────────────────────

    public function test_path_is_preserved_on_redirect(): void
    {
        $this->fromHost('netbazar.kz', '/catalog/kresla')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/catalog/kresla');
    }

    public function test_query_string_is_preserved_on_redirect(): void
    {
        $this->fromHost('netbazar.kz', '/catalog/kresla?page=2&ref=social')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/catalog/kresla?page=2&ref=social');
    }

    public function test_sitemap_redirects_to_canonical(): void
    {
        $this->fromHost('netbazar.kz', '/sitemap.xml')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/sitemap.xml');
    }

    // ── No-redirect cases ─────────────────────────────────────────────────────

    public function test_canonical_domain_is_not_redirected(): void
    {
        $this->fromHost('net-bazar.kz', '/')->assertOk();
    }

    public function test_unknown_host_is_not_redirected(): void
    {
        // Arbitrary Host values must never trigger a redirect (no open redirect).
        $this->fromHost('example.com', '/')->assertOk();
    }

    public function test_localhost_is_not_redirected(): void
    {
        $this->fromHost('localhost', '/')->assertOk();
    }

    // ── Method guard ──────────────────────────────────────────────────────────

    public function test_post_request_is_not_redirected_to_canonical(): void
    {
        // Redirecting POST would silently discard the request body.
        $response = $this->fromHost('netbazar.kz', '/cart/items', 'POST');
        $this->assertNotEquals(301, $response->getStatusCode(),
            'POST from technical domain must not be 301-redirected to canonical');
    }

    public function test_head_request_is_redirected(): void
    {
        $this->fromHost('netbazar.kz', '/', 'HEAD')
            ->assertStatus(301);
    }

    // ── Health endpoint ───────────────────────────────────────────────────────

    public function test_up_endpoint_is_not_redirected(): void
    {
        // Monitoring agents must not be forced to follow redirects.
        $this->fromHost('netbazar.kz', '/up')
            ->assertStatus(200);
    }

    // ── Loop guard ────────────────────────────────────────────────────────────

    public function test_no_redirect_when_canonical_host_equals_tech_domain(): void
    {
        // If NETBAZAR_SITE_URL is accidentally set to netbazar.kz the loop guard
        // must suppress the redirect rather than bouncing the user in a loop.
        config(['netbazar.contact.site_url' => 'https://netbazar.kz']);

        $this->fromHost('netbazar.kz', '/')->assertOk();
    }

    // ── Satu URL resolution — product ────────────────────────────────────────

    public function test_satu_product_resolves_to_url_slug_in_one_hop(): void
    {
        // Full product row (required NOT NULL columns) + url_slug.
        DB::table('source_categories')->insertOrIgnore([
            'id'   => 'test-src-cat-redirect',
            'name' => 'Test Category',
            'path' => 'test-src-cat-redirect',
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => 'CHAIR-TEST-77001',
            'source_offer_id'      => 'OFFER-77001',
            'name'                 => 'Ergonomic Chair',
            'brand'                => 'TestBrand',
            'price_kzt'            => '49900',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'test-src-cat-redirect',
            'source_category_path' => 'test-src-cat-redirect',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'url_slug'             => 'ergonomic-chair-test-77001',
        ]);
        DB::table('satu_product_redirects')->insertOrIgnore([
            'satu_id' => 77001,
            'sku'     => 'CHAIR-TEST-77001',
        ]);

        $this->fromHost('netbazar.kz', '/p77001-ergonomic-chair.html')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/product/ergonomic-chair-test-77001');
    }

    public function test_satu_product_without_url_slug_falls_back_to_sku(): void
    {
        // Only in satu_product_redirects; no products row → url_slug is null.
        DB::table('satu_product_redirects')->insertOrIgnore([
            'satu_id' => 77002,
            'sku'     => 'RAW-SKU-NO-SLUG',
        ]);

        $this->fromHost('netbazar.kz', '/p77002-some-title.html')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/product/RAW-SKU-NO-SLUG');
    }

    public function test_satu_product_not_in_db_redirects_path_to_canonical(): void
    {
        // Not in satu_product_redirects → middleware falls back to simple domain redirect.
        // SatuRedirectController will return 404 on net-bazar.kz (correct).
        $this->fromHost('netbazar.kz', '/p99999-some-unknown-product.html')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/p99999-some-unknown-product.html');
    }

    // ── Satu URL resolution — category (kaspi_id path) ───────────────────────

    public function test_satu_category_with_kaspi_id_resolves_to_catalog_slug_in_one_hop(): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'test-cat-chairs',
            'parent_id' => null,
            'name'      => 'Test Chairs',
            'path'      => 'test-cat-chairs',
            'source_id' => 'test-cat-chairs',
            'url_slug'  => 'test-kompyuternye-kresla',
        ]);
        DB::table('satu_category_redirects')->insertOrIgnore([
            'satu_id'    => 88001,
            'kaspi_id'   => 'test-cat-chairs',
            'target'     => null,
            'confidence' => 'high',
        ]);

        $this->fromHost('netbazar.kz', '/g88001-test-category-slug')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/catalog/test-kompyuternye-kresla');
    }

    public function test_satu_category_with_kaspi_id_but_no_slug_redirects_path_to_canonical(): void
    {
        // kaspi_id points to a category that has no url_slug yet.
        // Middleware returns null → falls back to simple path redirect.
        // SatuRedirectController will 404 on net-bazar.kz (expected — slug not yet generated).
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'test-cat-no-slug',
            'parent_id' => null,
            'name'      => 'No Slug Yet',
            'path'      => 'test-cat-no-slug',
            'source_id' => 'test-cat-no-slug',
            'url_slug'  => null,
        ]);
        DB::table('satu_category_redirects')->insertOrIgnore([
            'satu_id'    => 88002,
            'kaspi_id'   => 'test-cat-no-slug',
            'target'     => null,
            'confidence' => 'medium',
        ]);

        $this->fromHost('netbazar.kz', '/g88002-test-category-no-slug')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/g88002-test-category-no-slug');
    }

    // ── Satu URL resolution — category (static target path) ──────────────────

    public function test_satu_category_with_static_target_resolves_directly(): void
    {
        DB::table('satu_category_redirects')->insertOrIgnore([
            'satu_id'    => 88003,
            'kaspi_id'   => null,
            'target'     => '/catalog/kresla',
            'confidence' => 'high',
        ]);

        $this->fromHost('netbazar.kz', '/g88003-some-chairs')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/catalog/kresla');
    }

    public function test_satu_category_not_in_db_redirects_path_to_canonical(): void
    {
        $this->fromHost('netbazar.kz', '/g55555-unknown-category')
            ->assertStatus(301)
            ->assertRedirect(self::CANONICAL . '/g55555-unknown-category');
    }
}
