<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateUrlSlugsCommandTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedProduct(string $sku, string $name, ?string $urlSlug = null): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id'      => 'sc-slug-test',
            'name'    => 'Test Category',
            'path'    => 'Test Category',
            'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => $name,
            'brand'                => 'Brand',
            'price_kzt'            => '100',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-slug-test',
            'source_category_path' => 'Test Category',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'url_slug'             => $urlSlug,
            'last_imported_at'     => now(),
        ]);
    }

    private function getSlug(string $sku): ?string
    {
        return DB::table('products')->where('sku', $sku)->value('url_slug');
    }

    // ─── Basic slug generation ────────────────────────────────────────────────

    public function test_generates_slug_for_product_with_null_url_slug(): void
    {
        $this->seedProduct('LAVR-001', 'Lavr LN2137 очиститель топливной системы');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $this->assertSame(
            'lavr-ln2137-ochistitel-toplivnoj-sistemy',
            $this->getSlug('LAVR-001')
        );
    }

    public function test_user_canonical_example_lex_edp_610(): void
    {
        // This is the exact example from the SEO spec.
        $this->seedProduct('EDP-610', 'LEX EDP 610 BL Wide Series духовой шкаф');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $this->assertSame(
            'lex-edp-610-bl-wide-series-duhovoj-shkaf',
            $this->getSlug('EDP-610')
        );
    }

    public function test_vestfrost_freezer_with_cyrillic_name(): void
    {
        $this->seedProduct('VFIF2791E', 'Встраиваемый морозильник VestFrost VFI F2791E БЕЛЫЙ');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $this->assertSame(
            'vstraivaemyj-morozilnik-vestfrost-vfi-f2791e-belyj',
            $this->getSlug('VFIF2791E')
        );
    }

    public function test_slug_contains_only_safe_chars(): void
    {
        $this->seedProduct('TEST-1', 'SAMSUNG (Side-by-Side) SBS2795E — 2024!');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $slug = $this->getSlug('TEST-1');
        $this->assertNotNull($slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
        $this->assertStringNotContainsString('--', $slug);
    }

    // ─── Skip / force behaviour ───────────────────────────────────────────────

    public function test_existing_slug_is_not_overwritten_without_force(): void
    {
        $this->seedProduct('SK-001', 'Product Name', 'my-existing-slug');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $this->assertSame('my-existing-slug', $this->getSlug('SK-001'));
    }

    public function test_force_regenerates_existing_slug(): void
    {
        $this->seedProduct('SK-002', 'Холодильник Samsung RB37', 'wrong-old-slug');

        $this->artisan('netbazar:generate-url-slugs', ['--force' => true])->assertSuccessful();

        $slug = $this->getSlug('SK-002');
        $this->assertNotSame('wrong-old-slug', $slug);
        $this->assertStringContainsString('holodilnik', $slug);
    }

    public function test_idempotent_on_second_run(): void
    {
        $this->seedProduct('IDEM-1', 'LEX духовой шкаф EDP 610 BL');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();
        $slugFirst = $this->getSlug('IDEM-1');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();
        $slugSecond = $this->getSlug('IDEM-1');

        $this->assertSame($slugFirst, $slugSecond);
    }

    // ─── Conflict resolution ──────────────────────────────────────────────────

    public function test_duplicate_names_get_numeric_suffix(): void
    {
        // Both products have identical names — SKU-A is processed first (alphabetic order).
        $this->seedProduct('SKU-A', 'LEX EDP 610 духовой шкаф');
        $this->seedProduct('SKU-B', 'LEX EDP 610 духовой шкаф');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $a = $this->getSlug('SKU-A');
        $b = $this->getSlug('SKU-B');

        $this->assertSame('lex-edp-610-duhovoj-shkaf', $a);
        $this->assertSame('lex-edp-610-duhovoj-shkaf-2', $b);
        $this->assertNotSame($a, $b);
    }

    public function test_three_identical_names_get_sequential_suffixes(): void
    {
        $this->seedProduct('DUP-A', 'Кулер для воды Nobrand');
        $this->seedProduct('DUP-B', 'Кулер для воды Nobrand');
        $this->seedProduct('DUP-C', 'Кулер для воды Nobrand');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $this->assertSame('kuler-dlya-vody-nobrand',   $this->getSlug('DUP-A'));
        $this->assertSame('kuler-dlya-vody-nobrand-2', $this->getSlug('DUP-B'));
        $this->assertSame('kuler-dlya-vody-nobrand-3', $this->getSlug('DUP-C'));
    }

    public function test_conflict_with_preexisting_slug_in_db(): void
    {
        // Product A already owns the base slug.
        $this->seedProduct('EXIST-A', 'Холодильник LG GC-B459', 'holodilnik-lg-gc-b459');
        // Product B would generate the same slug from its name.
        $this->seedProduct('EXIST-B', 'Холодильник LG GC-B459');

        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        // B must get a suffix since A already occupies the base slug.
        $this->assertSame('holodilnik-lg-gc-b459',   $this->getSlug('EXIST-A')); // unchanged
        $this->assertSame('holodilnik-lg-gc-b459-2', $this->getSlug('EXIST-B'));
    }

    // ─── --dry-run ────────────────────────────────────────────────────────────

    public function test_dry_run_makes_no_db_changes(): void
    {
        $this->seedProduct('DRY-1', 'Вытяжка MEFERI SMARTBOX60IX');

        $this->artisan('netbazar:generate-url-slugs', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[DRY-RUN]');

        $this->assertNull($this->getSlug('DRY-1'));
    }

    public function test_dry_run_reports_count_without_writing(): void
    {
        $this->seedProduct('DRY-2', 'Product One');
        $this->seedProduct('DRY-3', 'Product Two');

        $this->artisan('netbazar:generate-url-slugs', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Would write 2 slug(s)');

        $this->assertNull($this->getSlug('DRY-2'));
        $this->assertNull($this->getSlug('DRY-3'));
    }

    // ─── --sku filter ─────────────────────────────────────────────────────────

    public function test_sku_filter_only_processes_specified_products(): void
    {
        $this->seedProduct('FILTER-A', 'Product A');
        $this->seedProduct('FILTER-B', 'Product B');
        $this->seedProduct('FILTER-C', 'Product C');

        $this->artisan('netbazar:generate-url-slugs', ['--sku' => ['FILTER-A', 'FILTER-C']])
            ->assertSuccessful();

        $this->assertNotNull($this->getSlug('FILTER-A'));
        $this->assertNull($this->getSlug('FILTER-B'));   // not requested
        $this->assertNotNull($this->getSlug('FILTER-C'));
    }

    // ─── --limit ─────────────────────────────────────────────────────────────

    public function test_limit_processes_at_most_n_products(): void
    {
        $this->seedProduct('LIM-A', 'Product A');
        $this->seedProduct('LIM-B', 'Product B');
        $this->seedProduct('LIM-C', 'Product C');

        $this->artisan('netbazar:generate-url-slugs', ['--limit' => 2])
            ->assertSuccessful();

        $filled = DB::table('products')
            ->where('sku', 'like', 'LIM-%')
            ->whereNotNull('url_slug')
            ->count();

        $this->assertSame(2, $filled);
    }

    // ─── Empty / nothing-to-do ────────────────────────────────────────────────

    public function test_nothing_to_do_when_all_slugs_filled(): void
    {
        $this->seedProduct('DONE-1', 'Product Done', 'already-done');

        $this->artisan('netbazar:generate-url-slugs')
            ->assertSuccessful()
            ->expectsOutputToContain('Nothing to do');
    }

    public function test_no_products_in_db_completes_successfully(): void
    {
        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();
    }

    // ─── Immutability under import ────────────────────────────────────────────

    public function test_url_slug_is_not_overwritten_by_simulated_import(): void
    {
        $this->seedProduct('IMP-1', 'Old Product Name');
        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $slugAfterGenerate = $this->getSlug('IMP-1');
        $this->assertNotNull($slugAfterGenerate);

        // Simulate a catalog import that changes the product name (but not url_slug).
        DB::table('products')
            ->where('sku', 'IMP-1')
            ->update(['name' => 'Completely New Product Name 2025']);

        // Running generate again without --force must leave url_slug unchanged.
        $this->artisan('netbazar:generate-url-slugs')->assertSuccessful();

        $this->assertSame($slugAfterGenerate, $this->getSlug('IMP-1'));
    }
}
