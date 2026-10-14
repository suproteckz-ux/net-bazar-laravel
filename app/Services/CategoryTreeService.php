<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoryTreeService
{
    /**
     * Load all active categories (leaf nodes with present+ready products, plus all ancestors).
     * Mirrors Python enrichment.public_categories() using the same recursive CTE logic.
     *
     * Applies display_name / display_order overrides when kaspi_category_overrides exists.
     * Falls back to the base query until the migration runs — safe for zero-downtime deploy.
     */
    public function getPublicCategories(): array
    {
        if (!Schema::hasTable('kaspi_category_overrides')) {
            return DB::select("
                WITH RECURSIVE used(id) AS (
                    SELECT a.category_id
                    FROM product_kaspi_categories a
                    JOIN products p ON p.sku = a.sku AND p.present = 1
                    JOIN kaspi_content e ON e.sku = a.sku AND e.status = 'ready'
                    JOIN kaspi_lookup kl ON kl.sku = e.sku AND kl.url = e.url AND kl.status = 'resolved'
                    UNION
                    SELECT c.parent_id
                    FROM kaspi_categories c
                    JOIN used u ON c.id = u.id
                    WHERE c.parent_id IS NOT NULL
                )
                SELECT kc.id, kc.parent_id, kc.name, kc.path, kc.url_slug
                FROM kaspi_categories kc
                WHERE kc.id IN (SELECT id FROM used)
                ORDER BY kc.name
            ");
        }

        return DB::select("
            WITH RECURSIVE used(id) AS (
                SELECT a.category_id
                FROM product_kaspi_categories a
                JOIN products p ON p.sku = a.sku AND p.present = 1
                JOIN kaspi_content e ON e.sku = a.sku AND e.status = 'ready'
                JOIN kaspi_lookup kl ON kl.sku = e.sku AND kl.url = e.url AND kl.status = 'resolved'
                UNION
                SELECT c.parent_id
                FROM kaspi_categories c
                JOIN used u ON c.id = u.id
                WHERE c.parent_id IS NOT NULL
            )
            SELECT kc.id, kc.parent_id,
                   COALESCE(o.display_name, kc.name) AS name,
                   kc.path,
                   kc.url_slug,
                   o.display_order
            FROM kaspi_categories kc
            LEFT JOIN kaspi_category_overrides o ON o.category_id = kc.id
            WHERE kc.id IN (SELECT id FROM used)
            ORDER BY COALESCE(o.display_order, 2147483647), kc.name
        ");
    }

    /**
     * Build nested tree from flat category list.
     * Each node gains a ->children array (empty for leaves).
     */
    public function buildTree(array $flat, ?string $parentId = null): array
    {
        $nodes = [];
        foreach ($flat as $cat) {
            if ($cat->parent_id === $parentId) {
                $node = clone $cat;
                $node->children = $this->buildTree($flat, $cat->id);
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    /**
     * Return all category IDs on the path from $categoryId up to the root.
     * Used to auto-expand the active branch in the tree.
     */
    public function getActiveBranchIds(string $categoryId, array $flat): array
    {
        $byId = [];
        foreach ($flat as $cat) {
            $byId[$cat->id] = $cat;
        }
        $ids = [];
        $current = $byId[$categoryId] ?? null;
        while ($current !== null) {
            $ids[] = $current->id;
            $current = $byId[$current->parent_id] ?? null;
        }
        return $ids;
    }

    /**
     * Find a single category object by ID.
     */
    public function findById(string $id, array $flat): ?object
    {
        foreach ($flat as $cat) {
            if ($cat->id === $id) {
                return $cat;
            }
        }
        return null;
    }

    /**
     * Build a unified tree merging kaspi categories and active manual categories.
     *
     * Node types:
     *   kaspi  → node_key='kaspi:{id}',  url links to /catalog/{slug} or /catalog?category={id}
     *   manual → node_key='manual:{id}', url links to /category/{slug}
     *
     * Manual categories are nested under their kaspi parent (if it's in the public tree),
     * their manual parent, or shown at root level. Orphaned kaspi parents (not in public
     * tree) cause the manual category to appear at root — stored kaspi_parent_id is preserved.
     */
    public function buildUnifiedTree(): array
    {
        $flatKaspi  = $this->getPublicCategories();
        $flatManual = $this->getActiveManualCategories();

        $kaspiIdSet = array_flip(array_column($flatKaspi, 'id'));
        $unified    = [];

        foreach ($flatKaspi as $cat) {
            $sortOrder  = property_exists($cat, 'display_order') ? ($cat->display_order ?? 9999) : 9999;
            $unified[]  = (object) [
                'node_key'   => 'kaspi:' . $cat->id,
                'id'         => $cat->id,
                'parent_key' => $cat->parent_id !== null ? 'kaspi:' . $cat->parent_id : null,
                'name'       => $cat->name,
                'node_type'  => 'kaspi',
                'url_slug'   => $cat->url_slug ?? null,
                'sort_order' => $sortOrder,
                'children'   => [],
            ];
        }

        foreach ($flatManual as $cat) {
            if ($cat->kaspi_parent_id !== null && isset($kaspiIdSet[$cat->kaspi_parent_id])) {
                $parentKey = 'kaspi:' . $cat->kaspi_parent_id;
            } elseif ($cat->parent_id !== null) {
                $parentKey = 'manual:' . $cat->parent_id;
            } else {
                $parentKey = null;
            }
            $unified[] = (object) [
                'node_key'   => 'manual:' . $cat->id,
                'id'         => (string) $cat->id,
                'parent_key' => $parentKey,
                'name'       => $cat->name,
                'node_type'  => 'manual',
                'url_slug'   => $cat->slug,
                'sort_order' => $cat->sort_order ?? 0,
                'children'   => [],
            ];
        }

        return $this->buildTreeFromNodes($unified, null);
    }

    /**
     * Return unified active path as an array of node_keys (e.g. ['kaspi:abc', 'kaspi:xyz']).
     * Used by controllers to highlight the active branch in the unified tree.
     */
    public function getUnifiedActivePath(string $nodeId, string $nodeType, array $flatKaspi): array
    {
        if ($nodeType === 'kaspi') {
            return array_map(
                fn ($id) => 'kaspi:' . $id,
                $this->getActiveBranchIds($nodeId, $flatKaspi)
            );
        }

        $path   = ['manual:' . $nodeId];
        $manual = DB::table('manual_categories')->where('id', $nodeId)->first();

        if ($manual === null) {
            return $path;
        }

        if (!empty($manual->kaspi_parent_id)) {
            foreach ($this->getActiveBranchIds($manual->kaspi_parent_id, $flatKaspi) as $id) {
                $path[] = 'kaspi:' . $id;
            }
        } elseif (!empty($manual->parent_id)) {
            $pid  = $manual->parent_id;
            $seen = [];
            while ($pid !== null && !isset($seen[$pid])) {
                $seen[$pid] = true;
                $path[]     = 'manual:' . $pid;
                $pid        = DB::table('manual_categories')->where('id', $pid)->value('parent_id');
            }
        }

        return $path;
    }

    private function buildTreeFromNodes(array $nodes, ?string $parentKey): array
    {
        $children = [];
        foreach ($nodes as $node) {
            if ($node->parent_key === $parentKey) {
                $n           = clone $node;
                $n->children = $this->buildTreeFromNodes($nodes, $node->node_key);
                $children[]  = $n;
            }
        }
        usort($children, function ($a, $b) {
            $diff = $a->sort_order <=> $b->sort_order;
            return $diff !== 0 ? $diff : strcmp($a->name, $b->name);
        });
        return $children;
    }

    private function getActiveManualCategories(): array
    {
        if (!Schema::hasTable('manual_categories')) {
            return [];
        }

        $hasKaspiParent = Schema::hasColumn('manual_categories', 'kaspi_parent_id');
        $select         = ['id', 'name', 'slug', 'parent_id', 'sort_order'];
        if ($hasKaspiParent) {
            $select[] = 'kaspi_parent_id';
        }

        return DB::table('manual_categories')
            ->where('is_active', true)
            ->select($select)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static function ($cat) use ($hasKaspiParent) {
                if (!$hasKaspiParent) {
                    $cat->kaspi_parent_id = null;
                }
                return $cat;
            })
            ->all();
    }

    /**
     * Get all descendant category IDs (including the category itself).
     * Used to filter products by category + all subcategories.
     */
    public function getDescendantIds(string $categoryId): array
    {
        $rows = DB::select("
            WITH RECURSIVE descendants(id) AS (
                SELECT id FROM kaspi_categories WHERE id = ?
                UNION
                SELECT c.id FROM kaspi_categories c
                JOIN descendants d ON c.parent_id = d.id
            )
            SELECT id FROM descendants
        ", [$categoryId]);
        return array_column($rows, 'id');
    }
}
