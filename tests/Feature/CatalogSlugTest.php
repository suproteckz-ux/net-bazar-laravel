<?php

namespace Tests\Feature;

use App\Models\HomeTile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogSlugTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Insert a minimal product + kaspi_content + kaspi_lookup row for rebuild tests. */
    private function seedKaspiProduct(string $sku, array $categoryPathNodes): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-rebuild', 'name' => 'Test', 'path' => 'Test', 'present' => 0,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => 'Rebuild Test Product',
            'brand'                => '',
            'price_kzt'            => '1000.00',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-rebuild',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);
        $url = 'https://kaspi.kz/shop/p/' . $sku . '-123';
        DB::table('kaspi_content')->insertOrIgnore([
            'sku'                => $sku,
            'url'                => $url,
            'description'        => '',
            'attributes_json'    => '[]',
            'photos_json'        => '[]',
            'category_path_json' => json_encode($categoryPathNodes, JSON_UNESCAPED_UNICODE),
            'breadcrumbs_json'   => '[]',
            'status'             => 'ready',
            'detail'             => '',
            'source_sha256'      => '',
        ]);
        DB::table('kaspi_lookup')->insertOrIgnore([
            'sku'        => $sku,
            'partner_id' => 'test-partner',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => $url,
            'detail'     => '',
            'frame_url'  => '',
        ]);
    }

    /** Compute the deterministic kaspi_category ID for a leaf node at the given path names. */
    private function kaspiCategoryId(array $names): string
    {
        $json  = str_replace('","', '", "', json_encode($names, JSON_UNESCAPED_UNICODE));
        return substr(hash('sha256', $json), 0, 24);
    }

    // ─── 1. Slug URL returns 200 ──────────────────────────────────────────────

    public function test_category_slug_url_returns_200(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-slug-test', 'name' => 'Тест', 'path' => 'Тест', 'url_slug' => 'test-slug-200', 'source_id' => 'test',
        ]);

        $this->get('/catalog/test-slug-200')->assertStatus(200);
    }

    // ─── 2. ?category=ID redirects 301 to slug ───────────────────────────────

    public function test_category_id_redirects_to_slug(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-redir-01', 'name' => 'Редирект', 'path' => 'Редирект', 'url_slug' => 'redir-slug', 'source_id' => 'test',
        ]);

        $this->get('/catalog?category=cat-redir-01')
            ->assertRedirectContains('/catalog/redir-slug');
    }

    // ─── 3. Query params preserved in ?category= redirect ────────────────────

    public function test_query_params_preserved_in_redirect(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-q-redir', 'name' => 'Поиск', 'path' => 'Поиск', 'url_slug' => 'poisk-redir', 'source_id' => 'test',
        ]);

        $response = $this->get('/catalog?category=cat-q-redir&q=%D0%BA%D1%80%D0%B5%D1%81%D0%BB%D0%BE');
        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/catalog/poisk-redir', $location);
        $this->assertStringContainsString('q=', $location);
    }

    // ─── 4. Canonical URL equals slug path (no query string) ─────────────────

    public function test_canonical_url_is_slug_path(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-canon', 'name' => 'Canonical', 'path' => 'Canonical', 'url_slug' => 'canonical-slug', 'source_id' => 'test',
        ]);

        $this->get('/catalog/canonical-slug')
            ->assertStatus(200)
            ->assertSee('/catalog/canonical-slug', false);
    }

    // ─── 5. Sitemap contains category slug ───────────────────────────────────

    public function test_sitemap_contains_slug(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-sitemap', 'name' => 'Sitemap', 'path' => 'Sitemap', 'url_slug' => 'sitemap-slug', 'source_id' => 'test',
        ]);

        $this->get('/sitemap-categories.xml')
            ->assertStatus(200)
            ->assertSee('sitemap-slug', false);
    }

    // ─── 6. Rebuild preserves url_slug ───────────────────────────────────────

    public function test_rebuild_preserves_url_slug(): void
    {
        $names = ['Мебель', 'Офисные кресла'];
        $this->seedKaspiProduct('SKU-REBUILD-01', [
            ['name' => 'Мебель',          'id' => 'src-a1'],
            ['name' => 'Офисные кресла',  'id' => 'src-b1'],
        ]);

        // First rebuild — creates categories from content
        $this->artisan('netbazar:rebuild-kaspi-categories', ['--force' => true])
            ->assertExitCode(0);

        $leafId = $this->kaspiCategoryId($names);
        $this->assertNotNull(
            DB::table('kaspi_categories')->where('id', $leafId)->first(),
            "Leaf category {$leafId} not created by first rebuild"
        );

        // Manually assign a url_slug (simulates running generate-category-slugs)
        DB::table('kaspi_categories')
            ->where('id', $leafId)
            ->update(['url_slug' => 'ofisnye-kresla']);

        // Second rebuild — should preserve the slug
        $this->artisan('netbazar:rebuild-kaspi-categories', ['--force' => true])
            ->assertExitCode(0);

        $slug = DB::table('kaspi_categories')->where('id', $leafId)->value('url_slug');
        $this->assertEquals('ofisnye-kresla', $slug);
    }

    // ─── 7. Slug not overwritten on rerun ────────────────────────────────────

    public function test_slug_not_overwritten_on_rerun(): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-idem', 'name' => 'Test', 'path' => 'Test', 'present' => 0,
        ]);
        DB::table('products')->insert([
            'sku'                  => 'SKU-IDEM',
            'source_offer_id'      => 'SKU-IDEM',
            'name'                 => 'Idem product',
            'brand'                => '',
            'price_kzt'            => '500.00',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-idem',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-idempot', 'name' => 'Идемпотент', 'path' => 'Идемпотент', 'url_slug' => null, 'source_id' => 'test',
        ]);
        DB::table('product_kaspi_categories')->insert(['sku' => 'SKU-IDEM', 'category_id' => 'cat-idempot']);
        $url = 'https://kaspi.kz/shop/p/SKU-IDEM-1';
        DB::table('kaspi_content')->insertOrIgnore([
            'sku' => 'SKU-IDEM', 'url' => $url, 'description' => '', 'attributes_json' => '[]',
            'photos_json' => '[]', 'category_path_json' => '[]', 'breadcrumbs_json' => '[]',
            'status' => 'ready', 'detail' => '', 'source_sha256' => '',
        ]);
        DB::table('kaspi_lookup')->insertOrIgnore([
            'sku' => 'SKU-IDEM', 'partner_id' => 'p', 'city_id' => '750000000',
            'status' => 'resolved', 'url' => $url, 'detail' => '', 'frame_url' => '',
        ]);

        // First run — generates slug
        $this->artisan('netbazar:generate-category-slugs')->assertExitCode(0);
        $slug1 = DB::table('kaspi_categories')->where('id', 'cat-idempot')->value('url_slug');
        $this->assertNotNull($slug1);

        // Second run — slug must be unchanged
        $this->artisan('netbazar:generate-category-slugs')->assertExitCode(0);
        $slug2 = DB::table('kaspi_categories')->where('id', 'cat-idempot')->value('url_slug');
        $this->assertEquals($slug1, $slug2);
    }

    // ─── 8. Duplicate category names get unique slugs ────────────────────────

    public function test_duplicate_names_get_unique_slugs(): void
    {
        DB::table('kaspi_categories')->insert([
            ['id' => 'dup-cat-1', 'name' => 'Кресла', 'path' => 'Мебель / Кресла', 'url_slug' => null, 'source_id' => 'test'],
            ['id' => 'dup-cat-2', 'name' => 'Кресла', 'path' => 'Офис / Кресла',   'url_slug' => null, 'source_id' => 'test'],
        ]);

        $this->artisan('netbazar:generate-category-slugs', ['--all' => true])
            ->assertExitCode(0);

        $slug1 = DB::table('kaspi_categories')->where('id', 'dup-cat-1')->value('url_slug');
        $slug2 = DB::table('kaspi_categories')->where('id', 'dup-cat-2')->value('url_slug');
        $this->assertNotNull($slug1);
        $this->assertNotNull($slug2);
        $this->assertNotEquals($slug1, $slug2, 'Duplicate names must produce unique slugs');
    }

    // ─── 9. HomeTile uses slug URL when kaspi category has one ───────────────

    public function test_home_tile_uses_slug_url(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-tile-slug', 'name' => 'Плитка', 'path' => 'Плитка', 'url_slug' => 'tile-slug', 'source_id' => 'test',
        ]);

        $tile = new HomeTile(['link_type' => 'kaspi_category', 'kaspi_category_id' => 'cat-tile-slug']);
        $url  = $tile->resolveUrl();

        $this->assertStringContainsString('/catalog/tile-slug', $url);
    }

    // ─── 10. HomeTile falls back to ?category= when no slug ─────────────────

    public function test_home_tile_falls_back_when_no_slug(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-no-slug', 'name' => 'No Slug', 'path' => 'No Slug', 'url_slug' => null, 'source_id' => 'test',
        ]);

        $tile = new HomeTile(['link_type' => 'kaspi_category', 'kaspi_category_id' => 'cat-no-slug']);
        $url  = $tile->resolveUrl();

        $this->assertStringContainsString('catalog?category=cat-no-slug', $url);
    }

    // ─── 11. Old slug redirects to new slug ──────────────────────────────────

    public function test_old_slug_redirects_to_new_slug(): void
    {
        DB::table('kaspi_categories')->insert([
            'id' => 'cat-renamed', 'name' => 'Новое', 'path' => 'Новое', 'url_slug' => 'new-slug', 'source_id' => 'test',
        ]);
        DB::table('kaspi_category_slug_redirects')->insert([
            'old_slug'          => 'old-slug',
            'kaspi_category_id' => 'cat-renamed',
        ]);

        $response = $this->get('/catalog/old-slug');
        $response->assertStatus(301);
        $this->assertStringContainsString('/catalog/new-slug', $response->headers->get('Location'));
    }
}
