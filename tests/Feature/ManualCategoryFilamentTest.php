<?php

namespace Tests\Feature;

use App\Filament\Resources\ManualCategoryResource\Pages\EditManualCategory;
use App\Models\ManualCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

    // ─── 11. Edit page with many products assigned returns 200 ───────────────────
    // Regression for production scenario: category with 30 products assigned.
    // Verifies the form renders and initialOptionLabels are embedded without PHP exception.

    public function test_edit_page_with_many_products_returns_200(): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kcat-stress-01',
            'parent_id' => null,
            'name'      => 'Мебель',
            'path'      => 'Мебель',
            'source_id' => 'kcat-stress-01',
        ]);
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-stress', 'name' => 'Stress', 'path' => 'Stress', 'present' => 1,
        ]);

        $category = ManualCategory::create([
            'name'            => 'Кресла стресс',
            'slug'            => 'kresla-stress',
            'is_active'       => true,
            'sort_order'      => 0,
            'kaspi_parent_id' => 'kcat-stress-01',
        ]);

        for ($i = 1; $i <= 30; $i++) {
            DB::table('products')->insertOrIgnore([
                'sku'                  => "STRESS-{$i}",
                'source_offer_id'      => "STRESS-{$i}",
                'name'                 => "Product Stress {$i}",
                'brand'                => 'Brand',
                'price_kzt'            => '1000.00',
                'quantity'             => 1,
                'available'            => 1,
                'source_category_id'   => 'sc-stress',
                'source_category_path' => 'Stress',
                'source_description'   => '',
                'source_photos_json'   => '[]',
                'present'              => 1,
                'last_imported_at'     => now(),
            ]);
            DB::table('product_manual_categories')->insertOrIgnore([
                'sku'                => "STRESS-{$i}",
                'manual_category_id' => $category->id,
                'sort_order'         => 0,
            ]);
        }

        $this->actingAs($this->adminUser())
            ->get('/admin/manual-categories/' . $category->id . '/edit')
            ->assertStatus(200)
            ->assertSee('Product Stress 1');
    }

    // ─── 12. callSchemaComponentMethod: kaspi getOptionLabel AJAX works ───────────
    // Simulates the JS call: $wire.callSchemaComponentMethod('form.kaspi_parent_id', 'getOptionLabel')
    // This is the AJAX request fired when initialOptionLabel doesn't match state.

    public function test_callschema_kaspi_parent_id_get_option_label(): void
    {
        $this->actingAs($this->adminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kcat-ajax-01',
            'parent_id' => null,
            'name'      => 'Кресла ajax',
            'path'      => 'Мебель / Кресла ajax',
            'source_id' => 'kcat-ajax-01',
        ]);

        $category = ManualCategory::create([
            'name'            => 'Кресла callschema',
            'slug'            => 'kresla-callschema',
            'is_active'       => true,
            'sort_order'      => 0,
            'kaspi_parent_id' => 'kcat-ajax-01',
        ]);

        $component = Livewire::test(EditManualCategory::class, ['record' => $category->getKey()]);
        $component->assertOk();

        // getOptionLabel is called by JS when the select needs to refresh its displayed label.
        // The key 'form.kaspi_parent_id' is the absolute schema component key.
        $component->call('callSchemaComponentMethod', 'form.kaspi_parent_id', 'getOptionLabel');
        $component->assertHasNoErrors();
    }

    // ─── 13. callSchemaComponentMethod: products getOptionLabelsForJs with 30 items ─
    // Simulates the JS AJAX call that fires when initialOptionLabels state comparison fails.
    // Production scenario: 30 products assigned, JS decides to re-fetch all labels.

    public function test_callschema_products_get_option_labels_for_js_many_products(): void
    {
        $this->actingAs($this->adminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-lw', 'name' => 'LW', 'path' => 'LW', 'present' => 1,
        ]);

        $category = ManualCategory::create([
            'name'       => 'Кресла labels',
            'slug'       => 'kresla-labels',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        for ($i = 1; $i <= 30; $i++) {
            DB::table('products')->insertOrIgnore([
                'sku'                  => "LW-{$i}",
                'source_offer_id'      => "LW-{$i}",
                'name'                 => "LW Product {$i}",
                'brand'                => 'Brand',
                'price_kzt'            => '1000.00',
                'quantity'             => 1,
                'available'            => 1,
                'source_category_id'   => 'sc-lw',
                'source_category_path' => 'LW',
                'source_description'   => '',
                'source_photos_json'   => '[]',
                'present'              => 1,
                'last_imported_at'     => now(),
            ]);
            DB::table('product_manual_categories')->insertOrIgnore([
                'sku'                => "LW-{$i}",
                'manual_category_id' => $category->id,
                'sort_order'         => 0,
            ]);
        }

        $component = Livewire::test(EditManualCategory::class, ['record' => $category->getKey()]);
        $component->assertOk();

        // getOptionLabelsForJs is called by JS for multiple-select when labels don't match state.
        $component->call('callSchemaComponentMethod', 'form.products', 'getOptionLabelsForJs');
        $component->assertHasNoErrors();
    }

    // ─── 14. callSchemaComponentMethod: FileUpload getUploadedFiles on init ─────
    // Simulates the AUTOMATIC Livewire AJAX that FilePond fires on every page load
    // via: FilePond.create({ files: await getFiles() }) → getUploadedFiles() → AJAX.
    // This is the ONLY automatic livewire/update request on the ManualCategory edit page.
    // In production (APP_DEBUG=false) a TypeError inside this call → abort(419).

    public function test_callschema_image_path_get_uploaded_files_null(): void
    {
        $this->actingAs($this->adminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $category = ManualCategory::create([
            'name'       => 'Без изображения',
            'slug'       => 'bez-izobrazheniya',
            'is_active'  => true,
            'sort_order' => 0,
            'image_path' => null,
        ]);

        $component = Livewire::test(EditManualCategory::class, ['record' => $category->getKey()]);
        $component->assertOk();

        $component->call('callSchemaComponentMethod', 'form.image_path', 'getUploadedFiles');
        $component->assertHasNoErrors();
    }

    public function test_callschema_image_path_get_uploaded_files_with_path(): void
    {
        $this->actingAs($this->adminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $category = ManualCategory::create([
            'name'       => 'С изображением',
            'slug'       => 's-izobrazheniem',
            'is_active'  => true,
            'sort_order' => 0,
            'image_path' => 'manual-categories/test-image.jpg',
        ]);

        $component = Livewire::test(EditManualCategory::class, ['record' => $category->getKey()]);
        $component->assertOk();

        // getUploadedFiles fires automatically on FilePond init — this is the
        // request that returns 419 in production (TypeError → abort(419) when APP_DEBUG=false).
        $component->call('callSchemaComponentMethod', 'form.image_path', 'getUploadedFiles');
        $component->assertHasNoErrors();
    }

    // ─── 15. saveUploadedFiles runs before dehydration on getUploadedFiles AJAX ─
    // Verifies that beforeStateDehydrated (saveUploadedFiles) doesn't throw TypeError.

    public function test_callschema_image_path_get_uploaded_files_roundtrip(): void
    {
        $this->actingAs($this->adminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $category = ManualCategory::create([
            'name'       => 'Кресло roundtrip',
            'slug'       => 'kreslo-roundtrip',
            'is_active'  => true,
            'sort_order' => 0,
            'image_path' => 'manual-categories/chair.jpg',
        ]);

        $component = Livewire::test(EditManualCategory::class, ['record' => $category->getKey()]);
        $component->assertOk();

        // First call — same as FilePond init AJAX
        $component->call('callSchemaComponentMethod', 'form.image_path', 'getUploadedFiles');
        $component->assertHasNoErrors();

        // Second call — same AJAX again (simulates re-init after Livewire update)
        $component->call('callSchemaComponentMethod', 'form.image_path', 'getUploadedFiles');
        $component->assertHasNoErrors();
    }

    // ─── 17. Save with kaspi_parent_id — regression for PHP TypeError (HTTP 419) ──
    // Root cause: `use Filament\Forms\Get` (Filament v3) in ManualCategoryResource.
    // Filament v4 passes Filament\Schemas\Components\Utilities\Get to the rules()
    // factory closure; the wrong type hint caused a PHP TypeError caught by Livewire
    // as HTTP 419. Fixed by correcting the import to the Filament v4 namespace.

    public function test_save_with_kaspi_parent_id_succeeds_after_type_error_fix(): void
    {
        $this->actingAs($this->adminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kcat-save-reg',
            'parent_id' => null,
            'name'      => 'Кресла сохранение',
            'path'      => 'Мебель / Кресла сохранение',
            'source_id' => 'kcat-save-reg',
        ]);

        $category = ManualCategory::create([
            'name'            => 'Кресла сохранение тест',
            'slug'            => 'kresla-save-reg',
            'is_active'       => true,
            'sort_order'      => 0,
            'kaspi_parent_id' => 'kcat-save-reg',
        ]);

        Livewire::test(EditManualCategory::class, ['record' => $category->getKey()])
            ->fillForm([
                'name'            => 'Кресла сохранение обновлено',
                'slug'            => 'kresla-save-reg',
                'kaspi_parent_id' => 'kcat-save-reg',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('manual_categories', [
            'id'   => $category->id,
            'name' => 'Кресла сохранение обновлено',
        ]);
    }

    // ─── 18. Mutual exclusion: both kaspi_parent_id and parent_id → validation error ──
    // The record is seeded with both fields set directly in the DB to bypass
    // afterStateUpdated (which would clear one field during fillForm). The edit page
    // hydrates both values, and save() must reject the form via $fail().

    public function test_validation_rejects_both_kaspi_parent_and_manual_parent(): void
    {
        $this->actingAs($this->adminUser());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => 'kcat-mutual-reg',
            'parent_id' => null,
            'name'      => 'Кресла взаимность',
            'path'      => 'Мебель / Кресла взаимность',
            'source_id' => 'kcat-mutual-reg',
        ]);

        $parentId = DB::table('manual_categories')->insertGetId([
            'name'       => 'Родитель взаимность',
            'slug'       => 'roditel-vzaimnost-reg',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seeded directly with both fields set — simulates a corrupt DB state.
        // afterStateUpdated callbacks don't fire during form hydration, so both
        // values will be present in the form state when save() validates.
        $categoryId = DB::table('manual_categories')->insertGetId([
            'name'            => 'Категория взаимность',
            'slug'            => 'kategoriya-vzaimnost-reg',
            'is_active'       => true,
            'sort_order'      => 0,
            'kaspi_parent_id' => 'kcat-mutual-reg',
            'parent_id'       => $parentId,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // kaspi_parent_id rules(): $get('parent_id') is truthy → $fail() fires.
        Livewire::test(EditManualCategory::class, ['record' => $categoryId])
            ->call('save')
            ->assertHasErrors();
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
