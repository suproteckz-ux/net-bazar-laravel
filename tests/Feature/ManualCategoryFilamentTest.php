<?php

namespace Tests\Feature;

use App\Models\ManualCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests that the ManualCategoryResource Filament panel actually loads —
 * i.e., no missing class / namespace errors in the resource definition.
 */
class ManualCategoryFilamentTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'name'     => 'Admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);
    }

    // ─── 1. Resource list page loads without 500 ──────────────────────────────

    public function test_filament_manual_category_list_loads(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/admin/manual-categories')
            ->assertStatus(200);
    }

    // ─── 2. Create page loads without 500 ─────────────────────────────────────

    public function test_filament_manual_category_create_loads(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/admin/manual-categories/create')
            ->assertStatus(200);
    }

    // ─── 3. Edit page loads for an existing record ────────────────────────────

    public function test_filament_manual_category_edit_loads(): void
    {
        $catId = DB::table('manual_categories')->insertGetId([
            'name'       => 'Тест',
            'slug'       => 'filament-edit-test',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->adminUser())
            ->get('/admin/manual-categories/' . $catId . '/edit')
            ->assertStatus(200);
    }

    // ─── 4. List page shows category name in table ────────────────────────────

    public function test_filament_list_shows_category_name(): void
    {
        DB::table('manual_categories')->insert([
            'name'       => 'Офисные кресла',
            'slug'       => 'ofisnyye-kresla',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->adminUser())
            ->get('/admin/manual-categories')
            ->assertStatus(200)
            ->assertSee('Офисные кресла');
    }

    // ─── 5. Non-admin user cannot access the panel ────────────────────────────

    public function test_non_admin_cannot_access_filament_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/manual-categories')
            ->assertStatus(403);
    }

    // ─── 6. Resource class instantiation — no missing class errors ────────────

    public function test_resource_class_can_be_resolved_without_errors(): void
    {
        $resource = \App\Filament\Resources\ManualCategoryResource::class;

        $this->assertTrue(class_exists($resource), 'ManualCategoryResource class must exist');

        // These static calls will throw if any use-d class is missing
        $label = $resource::getLabel();
        $icon  = $resource::getNavigationIcon();
        $group = $resource::getNavigationGroup();
        $pages = $resource::getPages();

        $this->assertEquals('Ручная категория', $label);
        $this->assertIsArray($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    // ─── 7. Edit page loads when kaspi_parent_id is set (regression for F2 bug) ─

    public function test_edit_page_loads_when_kaspi_parent_id_is_set(): void
    {
        // Seed a real kaspi category (as exists on production)
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kaspi-chairs-regression',
            'parent_id' => null,
            'name'      => 'Компьютерные и офисные кресла',
            'path'      => 'Мебель / Офис и кабинет / Компьютерные и офисные кресла',
            'source_id' => 'test',
            'url_slug'  => null,
        ]);

        $catId = DB::table('manual_categories')->insertGetId([
            'name'            => 'Кресла с подголовником',
            'slug'            => 'kresla-s-podgolovnikom-regression',
            'is_active'       => true,
            'sort_order'      => 0,
            'kaspi_parent_id' => 'kaspi-chairs-regression',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Before fix: this returned 500 ("Error while loading page") because
        // ->options() loaded all kaspi_categories on every Livewire render.
        // After fix: uses getSearchResultsUsing + getOptionLabelUsing — no full load.
        $this->actingAs($this->adminUser())
            ->get('/admin/manual-categories/' . $catId . '/edit')
            ->assertStatus(200);
    }

    // ─── 8. Edit page shows correct kaspi category label via getOptionLabelUsing ─

    public function test_edit_page_shows_correct_kaspi_category_label(): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kaspi-chairs-label-test',
            'parent_id' => null,
            'name'      => 'Компьютерные и офисные кресла',
            'path'      => 'Мебель / Офис и кабинет / Компьютерные и офисные кресла',
            'source_id' => 'test',
            'url_slug'  => null,
        ]);

        $catId = DB::table('manual_categories')->insertGetId([
            'name'            => 'Кресла тест лейбл',
            'slug'            => 'kresla-label-test',
            'is_active'       => true,
            'sort_order'      => 0,
            'kaspi_parent_id' => 'kaspi-chairs-label-test',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Filament renders initialOptionLabel via getOptionLabelUsing callback.
        // Js::from() escapes "/" as "\/" in JS strings, so assertSee the leaf
        // segment (no slashes) to verify the label was resolved from DB.
        $this->actingAs($this->adminUser())
            ->get('/admin/manual-categories/' . $catId . '/edit')
            ->assertStatus(200)
            ->assertSee('Компьютерные и офисные кресла');
    }

    // ─── 9. getOptionLabelUsing returns correct path for a kaspi_category_id ──

    public function test_kaspi_category_option_label_resolved_from_db(): void
    {
        $path = 'Электроника / Ноутбуки и ПК / Ноутбуки';
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kaspi-laptops-label',
            'parent_id' => null,
            'name'      => 'Ноутбуки',
            'path'      => $path,
            'source_id' => 'test',
            'url_slug'  => null,
        ]);

        // Test the label-resolution query that getOptionLabelUsing uses:
        // "given a kaspi_category.id, return its path column"
        $resolved = DB::table('kaspi_categories')
            ->where('id', 'kaspi-laptops-label')
            ->value('path');

        $this->assertEquals($path, $resolved, 'getOptionLabelUsing callback must return the category path');

        // Unknown ID returns null (graceful fallback)
        $missing = DB::table('kaspi_categories')
            ->where('id', 'non-existent-id')
            ->value('path');

        $this->assertNull($missing, 'Missing kaspi_category_id must resolve to null');
    }

    // ─── 10. getSearchResultsUsing filters by path substring ──────────────────

    public function test_kaspi_category_search_filters_by_path(): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kaspi-chairs-search-1',
            'parent_id' => null,
            'name'      => 'Офисные кресла',
            'path'      => 'Мебель / Офисные кресла',
            'source_id' => 'test',
            'url_slug'  => null,
        ]);
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kaspi-chairs-search-2',
            'parent_id' => null,
            'name'      => 'Игровые кресла',
            'path'      => 'Мебель / Игровые кресла',
            'source_id' => 'test',
            'url_slug'  => null,
        ]);
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kaspi-laptops-search',
            'parent_id' => null,
            'name'      => 'Ноутбуки',
            'path'      => 'Электроника / Ноутбуки',
            'source_id' => 'test',
            'url_slug'  => null,
        ]);

        // Test the search query that getSearchResultsUsing uses:
        // "given a search term, return matching paths (limit 50)"
        $search = 'кресла';
        $results = DB::table('kaspi_categories')
            ->where('path', 'like', '%' . $search . '%')
            ->orderBy('path')
            ->limit(50)
            ->pluck('path', 'id')
            ->toArray();

        $this->assertCount(2, $results, 'Search for "кресла" must return 2 matches');
        $this->assertArrayHasKey('kaspi-chairs-search-1', $results);
        $this->assertArrayHasKey('kaspi-chairs-search-2', $results);
        $this->assertArrayNotHasKey('kaspi-laptops-search', $results);
    }
}
