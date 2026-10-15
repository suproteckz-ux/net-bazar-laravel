<?php

namespace Tests\Feature;

use App\Models\ManualCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for the category tree navigation UX fix.
 *
 * Before fix: the entire row was an <a> with aria-expanded; clicking the
 * category name triggered JS e.preventDefault() → tree expanded but no navigation.
 *
 * After fix: parent nodes split into <a class="nb-tree__link"> (navigates) +
 * <button class="nb-tree__toggler" aria-expanded> (expands only).
 * Leaf nodes remain plain <a class="nb-tree__row"> anchors.
 */
class CategoryTreeViewTest extends TestCase
{
    use RefreshDatabase;

    // Seed a kaspi category + full product chain so it appears in the public tree.
    private function seedPublicKaspiCategory(
        string $id,
        string $name,
        ?string $parentId,
        string $urlSlug,
        string $sku
    ): void {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => $id,
            'parent_id' => $parentId,
            'name'      => $name,
            'path'      => $name,
            'source_id' => $id,
            'url_slug'  => $urlSlug,
        ]);

        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-ctv', 'name' => 'TV', 'path' => 'TV', 'present' => 1,
        ]);

        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => 'Product ' . $sku,
            'brand'                => '',
            'price_kzt'            => '1000',
            'quantity'             => 1,
            'available'            => 1,
            'source_category_id'   => 'sc-ctv',
            'source_category_path' => 'TV',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'last_imported_at'     => now(),
        ]);

        $url = 'https://kaspi.kz/shop/p/' . $sku;
        DB::table('kaspi_content')->insertOrIgnore([
            'sku'                => $sku,
            'url'                => $url,
            'description'        => '',
            'attributes_json'    => '[]',
            'photos_json'        => '[]',
            'category_path_json' => '[]',
            'breadcrumbs_json'   => '[]',
            'status'             => 'ready',
            'detail'             => '',
            'source_sha256'      => '',
        ]);

        DB::table('kaspi_lookup')->insertOrIgnore([
            'sku'        => $sku,
            'partner_id' => 'p',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => $url,
            'detail'     => '',
            'frame_url'  => '',
        ]);

        DB::table('product_kaspi_categories')->insertOrIgnore([
            'sku'         => $sku,
            'category_id' => $id,
        ]);
    }

    // ─── 1. Parent node: separate link + toggle button, no nested interactives ─
    // Regression: before fix, the row was a single <a> that JS intercepted —
    // clicking the name prevented navigation. After fix, link and button are
    // separate elements (no button inside a, no a inside button).

    public function test_parent_category_row_has_separate_link_and_toggle_button(): void
    {
        $this->seedPublicKaspiCategory('ctv-parent', 'Мебель', null, 'mebel-ctv', 'SKU-CTV-P');
        $this->seedPublicKaspiCategory('ctv-child',  'Кресла', 'ctv-parent', 'kresla-ctv', 'SKU-CTV-C');

        $body = $this->get('/catalog/mebel-ctv')->assertOk()->getContent();

        // Parent row must have a separate link element
        $this->assertStringContainsString('class="nb-tree__link"', $body,
            'Parent nodes must render a .nb-tree__link anchor for navigation');

        // Parent row must have a separate toggle button
        $this->assertStringContainsString('class="nb-tree__toggler"', $body,
            'Parent nodes must render a .nb-tree__toggler button for expand/collapse');

        // aria-expanded must be on the button, not on the link
        $this->assertMatchesRegularExpression(
            '/class="nb-tree__toggler"[^>]*aria-expanded=/',
            $body,
            'aria-expanded must be on .nb-tree__toggler'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/class="nb-tree__link"[^>]*aria-expanded=/',
            $body,
            'aria-expanded must NOT be on .nb-tree__link'
        );

        // aria-controls on button must reference an id on the branch div
        preg_match('/aria-controls="([^"]+)"/', $body, $m);
        $this->assertNotEmpty($m[1] ?? '', 'Toggle button must have aria-controls');
        $this->assertStringContainsString('id="' . ($m[1] ?? '') . '"', $body,
            'Branch div id must match aria-controls value');
    }

    // ─── 2. Leaf node: plain anchor only, no toggle button ───────────────────

    public function test_leaf_category_renders_as_plain_anchor_without_toggle(): void
    {
        // Single category, no children → renders as leaf
        $this->seedPublicKaspiCategory('ctv-leaf', 'Одиночная', null, 'odinochnaya-ctv', 'SKU-CTV-L');

        $body = $this->get('/catalog/odinochnaya-ctv')->assertOk()->getContent();

        // Page loads
        $this->assertStringContainsString('nb-tree__row', $body);

        // Leaf must NOT have a toggle button
        $this->assertStringNotContainsString('nb-tree__toggler', $body,
            'Leaf nodes must not render a toggle button');

        // Leaf must NOT have the split link element (it uses a.nb-tree__row directly)
        $this->assertStringNotContainsString('nb-tree__link', $body,
            'Leaf nodes must not render nb-tree__link — the row IS the anchor');
    }

    // ─── 3. Active ancestor branch is auto-expanded on page load ─────────────

    public function test_active_ancestor_branch_is_open_on_page_load(): void
    {
        $this->seedPublicKaspiCategory('ctv-open-p', 'ОткрытыйРаздел', null, 'otkrytyy-ctv', 'SKU-CTV-OP');
        $this->seedPublicKaspiCategory('ctv-open-c', 'ДочернийОткрытый', 'ctv-open-p', 'docherniy-ctv', 'SKU-CTV-OC');

        // Navigate to the child page — the parent branch must be open
        $body = $this->get('/catalog/docherniy-ctv')->assertOk()->getContent();

        // The parent's toggle button must report expanded state
        $this->assertStringContainsString('aria-expanded="true"', $body,
            'Active ancestor toggle button must be aria-expanded="true"');

        // The open branch div must not have the hidden attribute
        $this->assertMatchesRegularExpression(
            '/<div class="nb-tree__branch" id="[^"]+"[^>]*>/',
            $body,
            'Open branch div must not carry hidden attribute'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="nb-tree__branch" id="[^"]+"[^>]*hidden/',
            $body,
            'Open branch div must not have hidden attribute'
        );
    }

    // ─── 4. Parent link navigates to correct URL ─────────────────────────────

    public function test_parent_kaspi_category_link_points_to_slug_url(): void
    {
        $this->seedPublicKaspiCategory('ctv-slug-p', 'СлагРодитель', null, 'slag-roditel', 'SKU-CTV-SP');
        $this->seedPublicKaspiCategory('ctv-slug-c', 'СлагДочерний', 'ctv-slug-p', 'slag-docherniy', 'SKU-CTV-SC');

        $body = $this->get('/catalog/slag-roditel')->assertOk()->getContent();

        // The nb-tree__link for the parent must href to its own slug URL.
        // route() in tests generates full http://localhost:8000/... so check path segment.
        $this->assertStringContainsString('/catalog/slag-roditel"', $body,
            'Parent .nb-tree__link must point to /catalog/{slug}');
    }

    // ─── 5. Manual category page renders tree correctly ──────────────────────

    public function test_manual_category_page_renders_tree(): void
    {
        ManualCategory::create([
            'name'       => 'Ручная для теста дерева',
            'slug'       => 'ruchnaya-tree-view',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        $body = $this->get('/category/ruchnaya-tree-view')->assertOk()->getContent();
        $this->assertStringContainsString('Ручная для теста дерева', $body);
    }

    // ─── 6. Catalog index page renders tree without errors ────────────────────

    public function test_catalog_index_renders_without_errors(): void
    {
        $this->seedPublicKaspiCategory('ctv-idx-p', 'ИндексРодитель', null, 'index-roditel', 'SKU-CTV-IP');
        $this->seedPublicKaspiCategory('ctv-idx-c', 'ИндексДочерний', 'ctv-idx-p', 'index-docherniy', 'SKU-CTV-IC');

        $body = $this->get('/catalog')->assertOk()->getContent();
        $this->assertStringContainsString('nb-tree', $body);
    }
}
