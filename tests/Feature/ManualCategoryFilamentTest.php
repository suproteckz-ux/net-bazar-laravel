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
}
