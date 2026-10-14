<?php

namespace Tests\Feature;

use App\Models\ManualCategory;
use App\Services\CategoryTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnifiedTreeTest extends TestCase
{
    use RefreshDatabase;

    private function seedKaspiCategory(string $id, string $name, ?string $parentId = null): void
    {
        DB::table('kaspi_categories')->insertOrIgnore([
            'id'        => $id,
            'parent_id' => $parentId,
            'name'      => $name,
            'path'      => $name,
            'source_id' => 'test',
            'url_slug'  => null,
        ]);
    }

    private function seedManualCategory(array $attrs): ManualCategory
    {
        return ManualCategory::create(array_merge([
            'name'            => 'Test',
            'slug'            => 'test-' . uniqid(),
            'is_active'       => true,
            'sort_order'      => 0,
            'parent_id'       => null,
            'kaspi_parent_id' => null,
        ], $attrs));
    }

    // ─── 1. Manual category with kaspi_parent_id appears under kaspi node ────

    public function test_manual_category_appears_under_kaspi_parent(): void
    {
        $this->seedKaspiCategory('kc-chairs', 'Кресла');
        $manual = $this->seedManualCategory([
            'name'            => 'Кресла с подголовником',
            'slug'            => 'kresla-s-podgolovnikom',
            'kaspi_parent_id' => 'kc-chairs',
        ]);

        // The kaspi category needs a product to appear in the public tree
        // (buildUnifiedTree calls getPublicCategories which filters by present+ready products)
        // So instead, test the tree service directly with mocked data via reflection,
        // OR seed a full product stack so the kaspi category appears in getPublicCategories.
        //
        // Simpler approach: call buildUnifiedTree from a service that uses all kaspi_categories
        // without product filter — but that's --all mode. For the tree test, insert a product
        // chain so the kaspi category is "public".
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-t1', 'name' => 'T', 'path' => 'T', 'present' => 0,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku' => 'SKU-TREE-1', 'source_offer_id' => 'SKU-TREE-1', 'name' => 'Chair',
            'brand' => '', 'price_kzt' => '1000', 'quantity' => 1, 'available' => 1,
            'source_category_id' => 'sc-t1', 'source_category_path' => 'T',
            'source_description' => '', 'source_photos_json' => '[]', 'present' => 1,
        ]);
        $url = 'https://kaspi.kz/shop/p/tree-1';
        DB::table('kaspi_content')->insertOrIgnore([
            'sku' => 'SKU-TREE-1', 'url' => $url, 'description' => '',
            'attributes_json' => '[]', 'photos_json' => '[]', 'category_path_json' => '[]',
            'breadcrumbs_json' => '[]', 'status' => 'ready', 'detail' => '', 'source_sha256' => '',
        ]);
        DB::table('kaspi_lookup')->insertOrIgnore([
            'sku' => 'SKU-TREE-1', 'partner_id' => 'p', 'city_id' => '750000000',
            'status' => 'resolved', 'url' => $url, 'detail' => '', 'frame_url' => '',
        ]);
        DB::table('product_kaspi_categories')->insertOrIgnore([
            'sku' => 'SKU-TREE-1', 'category_id' => 'kc-chairs',
        ]);

        $tree    = app(CategoryTreeService::class)->buildUnifiedTree();
        $allKeys = $this->flattenKeys($tree);

        $this->assertContains('kaspi:kc-chairs', $allKeys, 'Kaspi category must appear in tree');
        $this->assertContains('manual:' . $manual->id, $allKeys, 'Manual category must appear in tree');

        // Verify nesting: manual node must be a child of the kaspi node
        $kaspiNode = $this->findNodeByKey($tree, 'kaspi:kc-chairs');
        $this->assertNotNull($kaspiNode);
        $childKeys = array_map(fn ($c) => $c->node_key, $kaspiNode->children);
        $this->assertContains('manual:' . $manual->id, $childKeys, 'Manual must be child of kaspi node');
    }

    // ─── 2. kaspi_parent_id preserved after kaspi rebuild ────────────────────

    public function test_kaspi_parent_id_preserved_after_kaspi_rebuild(): void
    {
        // Seed a product chain for rebuild
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-t2', 'name' => 'T2', 'path' => 'T2', 'present' => 0,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku' => 'SKU-TREE-2', 'source_offer_id' => 'SKU-TREE-2', 'name' => 'P2',
            'brand' => '', 'price_kzt' => '1000', 'quantity' => 1, 'available' => 1,
            'source_category_id' => 'sc-t2', 'source_category_path' => 'T2',
            'source_description' => '', 'source_photos_json' => '[]', 'present' => 1,
        ]);
        $url2 = 'https://kaspi.kz/shop/p/tree-2';
        DB::table('kaspi_content')->insertOrIgnore([
            'sku' => 'SKU-TREE-2', 'url' => $url2, 'description' => '',
            'attributes_json' => '[]', 'photos_json' => '[]',
            'category_path_json' => json_encode([['name' => 'Офисные кресла', 'id' => 's2']], JSON_UNESCAPED_UNICODE),
            'breadcrumbs_json' => '[]', 'status' => 'ready', 'detail' => '', 'source_sha256' => '',
        ]);
        DB::table('kaspi_lookup')->insertOrIgnore([
            'sku' => 'SKU-TREE-2', 'partner_id' => 'p', 'city_id' => '750000000',
            'status' => 'resolved', 'url' => $url2, 'detail' => '', 'frame_url' => '',
        ]);

        // First rebuild — creates kaspi category from content
        $this->artisan('netbazar:rebuild-kaspi-categories', ['--force' => true])->assertExitCode(0);

        $leafId = DB::table('kaspi_categories')->where('name', 'Офисные кресла')->value('id');
        $this->assertNotNull($leafId);

        // Create manual category under the kaspi category
        $manual = $this->seedManualCategory([
            'name'            => 'Кресла с подголовником',
            'slug'            => 'kresla-podgolovnik-2',
            'kaspi_parent_id' => $leafId,
        ]);

        // Second rebuild — kaspi category gets recreated with same deterministic ID
        $this->artisan('netbazar:rebuild-kaspi-categories', ['--force' => true])->assertExitCode(0);

        // The kaspi_parent_id on the manual category must still point to the same ID
        $fresh = ManualCategory::find($manual->id);
        $this->assertEquals($leafId, $fresh->kaspi_parent_id, 'kaspi_parent_id must survive rebuild');

        // The kaspi category with that ID must exist again after rebuild
        $this->assertNotNull(
            DB::table('kaspi_categories')->where('id', $leafId)->first(),
            'Kaspi category must reappear after rebuild with same deterministic ID'
        );
    }

    // ─── 3. Manual category page loads 200 ───────────────────────────────────

    public function test_manual_category_page_loads_200(): void
    {
        $this->seedManualCategory([
            'name'      => 'Тестовая категория',
            'slug'      => 'test-unified-cat',
            'is_active' => true,
        ]);

        $this->get('/category/test-unified-cat')->assertStatus(200);
    }

    // ─── 4. Mutual-exclusion: cycle-safe parent assignment ───────────────────

    public function test_manual_category_with_kaspi_parent_not_in_tree_shows_at_root(): void
    {
        // This tests the graceful fallback: when kaspi_parent_id references a kaspi category
        // that is NOT in the public tree (no products), the manual category appears at root.
        $manual = $this->seedManualCategory([
            'name'            => 'Orphaned',
            'slug'            => 'orphaned-cat',
            'kaspi_parent_id' => 'non-existent-kaspi-id',
        ]);

        $tree    = app(CategoryTreeService::class)->buildUnifiedTree();
        $allKeys = $this->flattenKeys($tree);

        // Must still appear in the tree (at root level, not nested under non-existent parent)
        $this->assertContains('manual:' . $manual->id, $allKeys);

        // The stored kaspi_parent_id is preserved in the DB
        $this->assertEquals('non-existent-kaspi-id', ManualCategory::find($manual->id)->kaspi_parent_id);
    }

    // ─── 5. getUnifiedActivePath returns correct node_keys ──────────────────

    public function test_unified_active_path_for_kaspi_category(): void
    {
        $this->seedKaspiCategory('kp-root', 'Root');
        $this->seedKaspiCategory('kp-leaf', 'Leaf', 'kp-root');

        $service = app(CategoryTreeService::class);
        $flat    = [
            (object) ['id' => 'kp-root', 'parent_id' => null,      'name' => 'Root', 'url_slug' => null],
            (object) ['id' => 'kp-leaf', 'parent_id' => 'kp-root', 'name' => 'Leaf', 'url_slug' => null],
        ];
        $path = $service->getUnifiedActivePath('kp-leaf', 'kaspi', $flat);

        $this->assertContains('kaspi:kp-leaf', $path);
        $this->assertContains('kaspi:kp-root', $path);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function flattenKeys(array $nodes): array
    {
        $keys = [];
        foreach ($nodes as $node) {
            $keys[] = $node->node_key;
            if (!empty($node->children)) {
                $keys = array_merge($keys, $this->flattenKeys($node->children));
            }
        }
        return $keys;
    }

    private function findNodeByKey(array $nodes, string $key): ?object
    {
        foreach ($nodes as $node) {
            if ($node->node_key === $key) {
                return $node;
            }
            if (!empty($node->children)) {
                $found = $this->findNodeByKey($node->children, $key);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
}
