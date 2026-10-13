<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SEO recovery for Satu "Офисные кресла Алматы" → /catalog/kompyuternye-i-ofisnye-kresla
 *
 * Checks:
 *   1. 301 from /g8531101-ofisnye-kresla-almaty → /catalog/kompyuternye-i-ofisnye-kresla
 *   2. HTTP 200 on the target page
 *   3. H1 = "Офисные кресла в Алматы"
 *   4. Title contains "Алматы"
 *   5. Unique meta description (distinct from /catalog/kresla)
 *   6. Canonical on https://net-bazar.kz
 *   7. Single-hop redirect (no chain)
 *   8. /catalog/kresla is independent — no redirect or conflict
 */
class OfficeChairsSeoTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG       = 'kompyuternye-i-ofisnye-kresla';
    private const SATU_PATH  = '/g8531101-ofisnye-kresla-almaty';
    private const TARGET_URL = '/catalog/kompyuternye-i-ofisnye-kresla';
    private const KASPI_ID   = 'k-comp-office-chairs';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedKaspiCategory();
        $this->seedSeoOverride();
        $this->seedSatuRedirect();
    }

    private function seedKaspiCategory(): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => self::KASPI_ID,
            'parent_id' => null,
            'name'      => 'Компьютерные и офисные кресла',
            'path'      => self::KASPI_ID,
            'source_id' => self::KASPI_ID,
            'url_slug'  => self::SLUG,
        ]);
    }

    private function seedSeoOverride(): void
    {
        DB::table('kaspi_category_overrides')->insertOrIgnore([
            'category_id'      => self::KASPI_ID,
            'h1'               => 'Офисные кресла в Алматы',
            'seo_title'        => 'Офисные кресла в Алматы — купить с доставкой | NetBazar',
            'meta_description' => 'Офисные кресла в Алматы — широкий выбор компьютерных и офисных кресел по доступным ценам. Быстрая доставка по Казахстану. Гарантия — NetBazar.',
            'display_name'     => null,
            'seo_text'         => null,
            'created_at'       => '2026-10-13 12:00:00',
            'updated_at'       => '2026-10-13 12:00:00',
        ]);
    }

    private function seedSatuRedirect(): void
    {
        // Migration 2026_10_13_000002 also inserts this row for production.
        // insertOrIgnore here is a no-op if migration already ran (in-memory SQLite: migration
        // inserts the row, setUp sees it exists → ignored, test proceeds correctly).
        DB::table('satu_category_redirects')->insertOrIgnore([
            'satu_id'    => 8531101,
            'kaspi_id'   => null,
            'target'     => self::TARGET_URL,
            'confidence' => 'high',
        ]);
    }

    // ─── 1. HTTP 301 redirect ────────────────────────────────────────────────

    public function test_satu_8531101_redirects_301_to_kompyuternye_i_ofisnye_kresla(): void
    {
        $this->get(self::SATU_PATH)
            ->assertStatus(301)
            ->assertRedirect(self::TARGET_URL);
    }

    // ─── 2. HTTP 200 on target page ──────────────────────────────────────────

    public function test_kompyuternye_kresla_page_returns_200(): void
    {
        $this->get(self::TARGET_URL)->assertStatus(200);
    }

    // ─── 3. H1 = "Офисные кресла в Алматы" ──────────────────────────────────

    public function test_kompyuternye_kresla_h1_is_ofisnye_kresla_v_almaty(): void
    {
        $this->get(self::TARGET_URL)
            ->assertStatus(200)
            ->assertSee('Офисные кресла в Алматы');
    }

    // ─── 4. Title with "Алматы" ──────────────────────────────────────────────

    public function test_kompyuternye_kresla_title_contains_almaty(): void
    {
        $content = (string) $this->get(self::TARGET_URL)->assertStatus(200)->getContent();

        // seo_title override must appear inside <title> tag
        $this->assertStringContainsString(
            'Офисные кресла в Алматы — купить с доставкой | NetBazar',
            $content,
        );
    }

    // ─── 5. Unique meta description ──────────────────────────────────────────

    public function test_kompyuternye_kresla_has_unique_meta_description(): void
    {
        $content = (string) $this->get(self::TARGET_URL)->assertStatus(200)->getContent();

        $this->assertStringContainsString('name="description"', $content,
            'Page must have a meta description');
        $this->assertStringContainsString('офисных кресел', $content,
            'Meta description must reference "офисных кресел"');
        // /catalog/kresla generates: "Купить кресла в Алматы и с доставкой по Казахстану."
        $this->assertStringNotContainsString('Купить кресла в Алматы и с доставкой', $content,
            'Must not duplicate /catalog/kresla meta description');
    }

    // ─── 6. Canonical on https://net-bazar.kz ────────────────────────────────

    public function test_kompyuternye_kresla_canonical_uses_net_bazar_kz(): void
    {
        config(['netbazar.contact.site_url' => 'https://net-bazar.kz']);

        $this->get(self::TARGET_URL)
            ->assertStatus(200)
            ->assertSee('href="https://net-bazar.kz' . self::TARGET_URL . '"', false);
    }

    // ─── 7. No redirect chain ────────────────────────────────────────────────

    public function test_satu_8531101_redirect_is_single_hop(): void
    {
        // Hop 1: Satu URL → 301 (not followed automatically by the test client)
        $this->get(self::SATU_PATH)->assertStatus(301);

        // Hop 2: the redirect target must serve 200, not issue another 3xx
        $this->get(self::TARGET_URL)->assertStatus(200);
    }

    // ─── 8. /catalog/kresla stays independent ────────────────────────────────

    public function test_kresla_marketing_page_is_independent_no_redirect(): void
    {
        // /catalog/kresla is a marketing page (step 2 in CatalogController::show()).
        // It has H1 = "Кресла" from SLUG_NAMES — intentionally broader than "офисные кресла".
        // It must render as-is (200) without redirecting to the kaspi category page.
        $response = $this->get('/catalog/kresla');

        $this->assertNotSame(301, $response->getStatusCode(),
            '/catalog/kresla must not become a redirect');
        $this->assertNotSame(302, $response->getStatusCode());
    }
}
