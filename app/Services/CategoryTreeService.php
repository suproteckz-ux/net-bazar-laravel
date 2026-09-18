<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CategoryTreeService
{
    /**
     * Load all active categories (leaf nodes with present+ready products, plus all ancestors).
     * Mirrors Python enrichment.public_categories() using the same recursive CTE logic.
     */
    public function getPublicCategories(): array
    {
        return DB::select("
            WITH RECURSIVE used(id) AS (
                SELECT a.category_id
                FROM product_kaspi_categories a
                JOIN products p ON p.sku = a.sku AND p.present = 1
                JOIN kaspi_content e ON e.sku = a.sku AND e.status = 'ready'
                UNION
                SELECT c.parent_id
                FROM kaspi_categories c
                JOIN used u ON c.id = u.id
                WHERE c.parent_id IS NOT NULL
            )
            SELECT kc.id, kc.parent_id, kc.name, kc.path
            FROM kaspi_categories kc
            WHERE kc.id IN (SELECT id FROM used)
            ORDER BY kc.name
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
