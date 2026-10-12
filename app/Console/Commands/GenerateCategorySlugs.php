<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generate and store url_slug for every public kaspi_category from its display name.
 *
 * Safety contract:
 *   – Without --force: only categories with url_slug IS NULL are touched.
 *   – With --force: existing slugs are regenerated (useful after algorithm fixes).
 *   – "Public" = category has at least one present+ready product in its subtree.
 *   – Never touches name, path, parent_id, or any Kaspi import field.
 *   – Idempotent: running twice on the same data produces the same slugs.
 *
 * Reuses the same transliteration algorithm as GenerateUrlSlugs (products).
 */
class GenerateCategorySlugs extends Command
{
    protected $signature = 'netbazar:generate-category-slugs
        {--dry-run : Show what would be generated; make no DB writes}
        {--force   : Re-generate even categories that already have url_slug}
        {--all     : Include categories without products (useful for pre-population)}';

    protected $description = 'Generate url_slug for public kaspi_categories from their display name (idempotent, immutable after first write)';

    private bool $dry;

    public function handle(): int
    {
        if (!Schema::hasColumn('kaspi_categories', 'url_slug')) {
            $this->error('Column kaspi_categories.url_slug does not exist. Run migrations first.');
            return 1;
        }

        $this->dry   = (bool) $this->option('dry-run');
        $force       = (bool) $this->option('force');
        $includeAll  = (bool) $this->option('all');
        $tag         = $this->dry ? '[DRY-RUN] ' : '';

        $this->info("{$tag}netbazar:generate-category-slugs");

        // Load all existing category slugs into pool to prevent conflicts.
        $slugPool = DB::table('kaspi_categories')
            ->whereNotNull('url_slug')
            ->pluck('id', 'url_slug')
            ->all(); // [slug => category_id]

        // Reserve the 5 marketing page slugs: /catalog/{slug} already serves a
        // marketing shortcut page for each, so no kaspi category may ever claim it.
        // Any conflicting kaspi category will get a -2 suffix instead.
        foreach (\App\Http\Controllers\CatalogController::marketingPageSlugs() as $reserved) {
            if (!isset($slugPool[$reserved])) {
                $slugPool[$reserved] = '_reserved';
            }
        }

        // Load display names (overrides first, raw name as fallback).
        $categories = $this->loadCategories($force, $includeAll);
        $total = count($categories);
        $this->line("  to process: {$total} | existing slugs in pool: " . count($slugPool));

        if ($total === 0) {
            $this->info('Nothing to do — all public categories already have url_slug. Use --force to regenerate.');
            return 0;
        }

        // When --force, remove each category's own slug from pool so it can reclaim it.
        if ($force) {
            foreach ($categories as $cat) {
                if (!empty($cat->url_slug)) {
                    unset($slugPool[$cat->url_slug]);
                }
            }
        }

        $results  = [];
        $conflicts = 0;

        foreach ($categories as $cat) {
            $displayName = $cat->display_name ?? $cat->name;
            $candidate   = GenerateUrlSlugs::generateSlug($displayName);

            if ($candidate === '') {
                $this->warn("  [NEEDS_REVIEW] {$cat->id} — empty slug from: " . mb_substr($displayName, 0, 60));
                $results[] = ['id' => $cat->id, 'slug' => '', 'status' => 'NEEDS_REVIEW'];
                continue;
            }

            [$slug, $attempt] = $this->resolveSlug($candidate, $cat->id, $slugPool);

            if ($slug === '') {
                $this->warn("  [CONFLICT] {$cat->id} — could not resolve: {$candidate}");
                $conflicts++;
                $results[] = ['id' => $cat->id, 'slug' => '', 'status' => 'CONFLICT'];
                continue;
            }

            $slugPool[$slug] = $cat->id;
            $results[] = ['id' => $cat->id, 'slug' => $slug, 'status' => 'NEW', 'attempt' => $attempt];
        }

        // Write
        $written = 0;
        if (!$this->dry) {
            foreach ($results as $r) {
                if ($r['slug'] === '') continue;
                $q = DB::table('kaspi_categories')->where('id', $r['id']);
                if (!$force) $q->whereNull('url_slug');
                $q->update(['url_slug' => $r['slug']]);
                $written++;
            }
        } else {
            foreach ($results as $r) {
                if ($r['slug'] !== '') {
                    $this->line(sprintf('  %s → %s', str_pad(substr($r['id'], 0, 24), 24), $r['slug']));
                }
            }
            $written = count(array_filter($results, fn($r) => $r['slug'] !== ''));
        }

        $this->newLine();
        $this->info($tag . "Written: {$written} | Conflicts: {$conflicts}");

        return $conflicts > 0 ? 1 : 0;
    }

    private function loadCategories(bool $force, bool $includeAll): array
    {
        // Base query with override name
        $query = DB::table('kaspi_categories as kc')
            ->leftJoin('kaspi_category_overrides as o', 'o.category_id', '=', 'kc.id')
            ->select(
                'kc.id',
                'kc.name',
                DB::raw('COALESCE(o.display_name, kc.name) AS display_name'),
                'kc.url_slug'
            );

        if (!$force) {
            $query->whereNull('kc.url_slug');
        }

        if (!$includeAll) {
            // Only categories in the "public" tree: have at least one present+ready product.
            // Include both leaf categories AND all their ancestors.
            $publicIds = $this->publicCategoryIds();
            if (empty($publicIds)) {
                return [];
            }
            $query->whereIn('kc.id', $publicIds);
        }

        return $query->orderBy('kc.id')->get()->all();
    }

    /**
     * Return all kaspi_category IDs that are in the public tree:
     * leaf categories with ≥1 present+ready product, plus all their ancestors.
     *
     * Falls back gracefully when kaspi_content/kaspi_lookup don't exist (test env).
     */
    private function publicCategoryIds(): array
    {
        if (!Schema::hasTable('kaspi_content') || !Schema::hasTable('kaspi_lookup')) {
            // Test environment: return ALL category IDs (tests seed their own data)
            return DB::table('kaspi_categories')->pluck('id')->all();
        }

        $rows = DB::select("
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
            SELECT id FROM used
        ");

        return array_column($rows, 'id');
    }

    /**
     * Find a unique slug, appending -2, -3, … up to 99 attempts.
     * Returns ['', 0] on exhaustion (practically impossible).
     */
    private function resolveSlug(string $candidate, string $catId, array $slugPool): array
    {
        $slug    = $candidate;
        $attempt = 1;

        while ($attempt <= 99) {
            $takenBy = $slugPool[$slug] ?? null;
            if ($takenBy === null || $takenBy === $catId) {
                return [$slug, $attempt];
            }
            $attempt++;
            $slug = $candidate . '-' . $attempt;
        }

        return ['', 0];
    }
}
