<?php

namespace Tests\Feature;

use App\Models\HomeTile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for Phase D — home tiles managed via Filament and rendered from DB.
 */
class HomeTileTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function tile(array $overrides = []): HomeTile
    {
        return HomeTile::create(array_merge([
            'label'             => 'Тестовая плитка',
            'image_path'        => '/images/categories/auto.png',
            'image_alt'         => 'Тест',
            'link_type'         => 'kaspi_category',
            'kaspi_category_id' => 'test-kaspi-cat-id',
            'sort_order'        => 10,
            'is_active'         => true,
        ], $overrides));
    }

    // ─── 1. Filament list page loads ──────────────────────────────────────────

    public function test_filament_home_tile_list_loads(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/admin/home-tiles')
            ->assertStatus(200);
    }

    // ─── 2. Filament create page loads ────────────────────────────────────────

    public function test_filament_home_tile_create_page_loads(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/admin/home-tiles/create')
            ->assertStatus(200);
    }

    // ─── 3. Filament edit page loads ──────────────────────────────────────────

    public function test_filament_home_tile_edit_page_loads(): void
    {
        $tile = $this->tile();

        $this->actingAs($this->adminUser())
            ->get('/admin/home-tiles/' . $tile->id . '/edit')
            ->assertStatus(200);
    }

    // ─── 4. Migration seeds exactly 5 tiles ───────────────────────────────────

    public function test_migration_seeds_exactly_5_tiles(): void
    {
        $this->assertEquals(5, HomeTile::count());
    }

    // ─── 5. Seeding is idempotent ─────────────────────────────────────────────

    public function test_seeding_is_idempotent(): void
    {
        // Simulate re-running the seed (insertOrIgnore on seed_key)
        DB::table('home_tiles')->insertOrIgnore([
            'label'             => 'Автотовары',
            'image_path'        => '/images/categories/auto.png',
            'image_alt'         => 'Автотовары',
            'link_type'         => 'kaspi_category',
            'kaspi_category_id' => '6bede016037a3d7e79338836',
            'sort_order'        => 1,
            'is_active'         => 1,
            'seed_key'          => 'legacy_auto',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->assertEquals(5, HomeTile::count(), 'Duplicate seed must not create extra tiles');
    }

    // ─── 6. Home page shows tiles from DB ─────────────────────────────────────

    public function test_home_page_shows_tiles_from_db(): void
    {
        $this->tile(['label' => 'Плитка из БД', 'sort_order' => 99]);

        $this->get(route('home'))
            ->assertStatus(200)
            ->assertSee('Плитка из БД');
    }

    // ─── 7. Inactive tile not shown on home page ──────────────────────────────

    public function test_inactive_tile_not_shown(): void
    {
        $this->tile(['label' => 'Скрытая плитка', 'is_active' => false]);

        $this->get(route('home'))
            ->assertStatus(200)
            ->assertDontSee('Скрытая плитка');
    }

    // ─── 8. Tiles ordered by sort_order ──────────────────────────────────────

    public function test_tiles_ordered_by_sort_order(): void
    {
        HomeTile::query()->delete();

        HomeTile::create(['label' => 'Третья', 'link_type' => 'kaspi_category', 'kaspi_category_id' => 'c3', 'sort_order' => 30, 'is_active' => true]);
        HomeTile::create(['label' => 'Первая', 'link_type' => 'kaspi_category', 'kaspi_category_id' => 'c1', 'sort_order' => 10, 'is_active' => true]);
        HomeTile::create(['label' => 'Вторая', 'link_type' => 'kaspi_category', 'kaspi_category_id' => 'c2', 'sort_order' => 20, 'is_active' => true]);

        $response = $this->get(route('home'))->assertStatus(200);
        $content  = $response->getContent();

        $pos1 = strpos($content, 'Первая');
        $pos2 = strpos($content, 'Вторая');
        $pos3 = strpos($content, 'Третья');

        $this->assertLessThan($pos2, $pos1, 'sort_order 10 should come before 20');
        $this->assertLessThan($pos3, $pos2, 'sort_order 20 should come before 30');
    }

    // ─── 9. Kaspi category tile generates correct catalog URL ─────────────────

    public function test_kaspi_tile_generates_catalog_url(): void
    {
        $tile = new HomeTile([
            'link_type'         => 'kaspi_category',
            'kaspi_category_id' => 'abc123',
        ]);
        $url = $tile->resolveUrl();
        $this->assertStringContainsString('/catalog?category=abc123', $url);
    }

    // ─── 10. Manual category tile generates /category/ URL ────────────────────

    public function test_manual_category_tile_generates_correct_url(): void
    {
        $manualCatId = DB::table('manual_categories')->insertGetId([
            'name'       => 'Кресла с подголовником',
            'slug'       => 'kresla-podgolovnik',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tile = HomeTile::create([
            'label'              => 'Кресла',
            'link_type'          => 'manual_category',
            'manual_category_id' => $manualCatId,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $tile->load('manualCategory');

        $this->assertStringContainsString('/category/kresla-podgolovnik', $tile->resolveUrl());
    }

    // ─── 11. Custom URL tile is rendered safely ────────────────────────────────

    public function test_custom_url_tile_allows_root_relative_paths(): void
    {
        $tile = new HomeTile(['link_type' => 'custom_url', 'custom_url' => '/catalog/chairs']);
        $this->assertEquals('/catalog/chairs', $tile->resolveUrl());
    }

    public function test_custom_url_tile_blocks_external_urls(): void
    {
        $tile = new HomeTile(['link_type' => 'custom_url', 'custom_url' => 'https://evil.com']);
        $this->assertNull($tile->resolveUrl(), 'External URL must be rejected');
    }

    public function test_custom_url_tile_blocks_protocol_relative_urls(): void
    {
        $tile = new HomeTile(['link_type' => 'custom_url', 'custom_url' => '//evil.com']);
        $this->assertNull($tile->resolveUrl(), 'Protocol-relative URL must be rejected');
    }

    // ─── 12. Five legacy tiles preserved with correct labels ─────────────────

    public function test_five_legacy_tiles_are_present(): void
    {
        $labels = HomeTile::pluck('label')->all();
        $this->assertContains('Автотовары', $labels);
        $this->assertContains('Кресла', $labels);
        $this->assertContains('Товары для дома', $labels);
        $this->assertContains('Красота и здоровье', $labels);
        $this->assertContains('Бытовая техника', $labels);
    }

    // ─── 13. Home page works with no active tiles ─────────────────────────────

    public function test_home_works_with_no_active_tiles(): void
    {
        HomeTile::query()->update(['is_active' => false]);

        $this->get(route('home'))
            ->assertStatus(200);
    }

    // ─── 14. Non-admin cannot access Filament home-tiles ─────────────────────

    public function test_non_admin_cannot_access_filament_home_tiles(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/home-tiles')
            ->assertStatus(403);
    }
}
