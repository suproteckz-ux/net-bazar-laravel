<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generate and store url_slug for every product from its full display name.
 *
 * Safety contract:
 *   – Without --force: only products with url_slug IS NULL are touched.
 *   – With --force: existing slugs are regenerated (useful after algorithm fixes).
 *   – DB write is guarded by whereNull('url_slug') when --force is absent.
 *   – Never touches name, brand, price, sku, or any import field.
 *   – Idempotent: running twice on the same data produces the same slugs.
 */
class GenerateUrlSlugs extends Command
{
    protected $signature = 'netbazar:generate-url-slugs
        {--dry-run    : Show what would be generated; make no DB writes}
        {--force      : Re-generate even products that already have url_slug}
        {--sku=*      : Process only these SKUs (repeatable)}
        {--limit=0    : Process at most N products (0 = no limit)}
        {--batch=200  : Products per DB transaction}';

    protected $description = 'Generate url_slug for products from their full name (idempotent, immutable after first write)';

    // ── Status constants ──────────────────────────────────────────────────────

    const S_NEW          = 'NEW';           // slug generated and written
    const S_SKIP         = 'SKIP';          // already has url_slug, --force not set
    const S_CONFLICT     = 'CONFLICT';      // could not find a unique slug in 99 attempts
    const S_SKU_SHADOW   = 'SKU_SHADOW';    // slug coincides with another product's raw SKU
    const S_NEEDS_REVIEW = 'NEEDS_REVIEW';  // slug is too short or empty

    private bool $dry;

    // ── Entry point ───────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->dry  = (bool) $this->option('dry-run');
        $force      = (bool) $this->option('force');
        $skuFilter  = (array) $this->option('sku');
        $limit      = (int)  $this->option('limit');
        $batchSize  = max(1, (int) $this->option('batch'));
        $tag        = $this->dry ? '[DRY-RUN] ' : '';

        $this->info("{$tag}netbazar:generate-url-slugs");
        $this->line(sprintf('  force=%-5s  limit=%s  batch=%d',
            $force ? 'yes' : 'no', $limit ?: 'none', $batchSize));

        // Bulk-load existing slugs and all SKUs — no N+1 queries.
        $this->line('Loading DB state…');
        [$slugPool, $allSkuSet] = $this->loadDbState();
        $this->line(sprintf('  url_slug filled=%d  total_products=%d',
            count($slugPool), count($allSkuSet)));

        // Fetch the products to process.
        $query = DB::table('products')->select(['sku', 'name', 'url_slug']);
        if (!$force) {
            $query->whereNull('url_slug');
        }
        if ($skuFilter) {
            $query->whereIn('sku', $skuFilter);
        }
        $query->orderBy('sku');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get()->all();
        $total    = count($products);
        $this->line("  to process: {$total}");

        if ($total === 0) {
            $this->info('Nothing to do — all products already have url_slug. Use --force to regenerate.');
            return 0;
        }

        // With --force, remove each processed product's own existing slug from the
        // pool so it can reclaim the same slug without a spurious conflict.
        if ($force) {
            foreach ($products as $p) {
                if ($p->url_slug !== null) {
                    unset($slugPool[$p->url_slug]);
                }
            }
        }

        // Generate slugs and collect results.
        $stats   = array_fill_keys(
            [self::S_NEW, self::S_SKIP, self::S_CONFLICT, self::S_SKU_SHADOW, self::S_NEEDS_REVIEW], 0
        );
        $rows    = [];
        $flagged = [];

        foreach ($products as $p) {
            if ($p->url_slug !== null && !$force) {
                $stats[self::S_SKIP]++;
                continue;
            }

            $candidate = self::generateSlug($p->name);
            [$finalSlug, $status] = $this->resolveSlug($candidate, $p->sku, $slugPool, $allSkuSet);

            $stats[$status]++;

            // Add the assigned slug to the in-memory pool so subsequent products
            // in the same run can detect intra-batch conflicts.
            if ($finalSlug !== '') {
                $slugPool[$finalSlug] = $p->sku;
            }

            $row = [
                'sku'      => $p->sku,
                'name'     => $p->name,
                'old_slug' => $p->url_slug,
                'new_slug' => $finalSlug,
                'status'   => $status,
            ];
            $rows[] = $row;

            if (in_array($status, [self::S_CONFLICT, self::S_SKU_SHADOW, self::S_NEEDS_REVIEW], true)) {
                $flagged[] = $row;
            }
        }

        // Print items that need attention.
        if ($flagged) {
            $this->newLine();
            $this->warn(count($flagged) . ' item(s) need attention:');
            foreach ($flagged as $r) {
                $this->line(sprintf(
                    '  [%-12s] %s',
                    $r['status'],
                    mb_substr($r['sku'], 0, 30)
                ));
                $this->line(sprintf(
                    '             name : %s',
                    mb_substr($r['name'], 0, 100)
                ));
                $this->line(sprintf(
                    '             slug : %s',
                    $r['new_slug'] ?: '(none)'
                ));
            }
        }

        // Write slugs that have a valid result.
        $toWrite = array_filter($rows, fn($r) => in_array($r['status'],
            [self::S_NEW, self::S_SKU_SHADOW, self::S_NEEDS_REVIEW], true));

        if (!$this->dry && $toWrite) {
            foreach (array_chunk(array_values($toWrite), $batchSize) as $chunk) {
                DB::transaction(function () use ($chunk, $force) {
                    foreach ($chunk as $r) {
                        $q = DB::table('products')->where('sku', $r['sku']);
                        // Safety guard: without --force, never overwrite an existing slug
                        // even if somehow the record slipped through the query filter.
                        if (!$force) {
                            $q->whereNull('url_slug');
                        }
                        $q->update(['url_slug' => $r['new_slug']]);
                    }
                });
            }
        }

        $this->printSummary($tag, $stats, count($rows));

        return $stats[self::S_CONFLICT] > 0 ? 1 : 0;
    }

    // ── Slug generation (public static — testable without DB) ─────────────────

    /**
     * Generate a URL slug from a product's full display name.
     *
     * Algorithm:
     *   1. Transliterate Cyrillic → Latin (Х→h, Й→j, Ь/Ъ→"", etc.)
     *   2. Lowercase
     *   3. Replace all non-alphanumeric runs with a single hyphen
     *   4. Trim leading/trailing hyphens
     *   5. Truncate at a word boundary if longer than $maxLength
     */
    public static function generateSlug(string $name, int $maxLength = 100): string
    {
        $s = self::transliterate($name);
        $s = mb_strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = preg_replace('/-{2,}/', '-', $s);
        $s = trim($s, '-');

        if (mb_strlen($s) > $maxLength) {
            $s         = mb_substr($s, 0, $maxLength);
            $lastDash  = strrpos($s, '-');
            if ($lastDash !== false && $lastDash >= (int) ($maxLength * 0.7)) {
                $s = substr($s, 0, $lastDash);
            }
            $s = rtrim($s, '-');
        }

        return $s;
    }

    /**
     * Transliterate Cyrillic characters to Latin equivalents.
     *
     * Rules match common Russian SEO practice (e.g. Satu.kz slugs):
     *   Х → h  (not kh)
     *   Й → j
     *   Ь/Ъ → "" (soft/hard signs dropped)
     */
    public static function transliterate(string $str): string
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'А' => 'a',   'Б' => 'b',    'В' => 'v',    'Г' => 'g',  'Д' => 'd',
                'Е' => 'e',   'Ё' => 'yo',   'Ж' => 'zh',   'З' => 'z',  'И' => 'i',
                'Й' => 'j',   'К' => 'k',    'Л' => 'l',    'М' => 'm',  'Н' => 'n',
                'О' => 'o',   'П' => 'p',    'Р' => 'r',    'С' => 's',  'Т' => 't',
                'У' => 'u',   'Ф' => 'f',    'Х' => 'h',    'Ц' => 'ts', 'Ч' => 'ch',
                'Ш' => 'sh',  'Щ' => 'shch', 'Ъ' => '',     'Ы' => 'y',  'Ь' => '',
                'Э' => 'e',   'Ю' => 'yu',   'Я' => 'ya',
                'а' => 'a',   'б' => 'b',    'в' => 'v',    'г' => 'g',  'д' => 'd',
                'е' => 'e',   'ё' => 'yo',   'ж' => 'zh',   'з' => 'z',  'и' => 'i',
                'й' => 'j',   'к' => 'k',    'л' => 'l',    'м' => 'm',  'н' => 'n',
                'о' => 'o',   'п' => 'p',    'р' => 'r',    'с' => 's',  'т' => 't',
                'у' => 'u',   'ф' => 'f',    'х' => 'h',    'ц' => 'ts', 'ч' => 'ch',
                'ш' => 'sh',  'щ' => 'shch', 'ъ' => '',     'ы' => 'y',  'ь' => '',
                'э' => 'e',   'ю' => 'yu',   'я' => 'ya',
            ];
        }
        return strtr($str, $map);
    }

    // ── Conflict resolution ───────────────────────────────────────────────────

    /**
     * Find a unique slug, appending -2, -3, … if needed.
     * Returns ['', CONFLICT] only if 99 attempts are exhausted (practically impossible).
     */
    private function resolveSlug(string $candidate, string $sku, array $slugPool, array $allSkuSet): array
    {
        if ($candidate === '') {
            return ['', self::S_NEEDS_REVIEW];
        }

        $slug    = $candidate;
        $attempt = 1;

        while (true) {
            $takenBy = $slugPool[$slug] ?? null;
            if ($takenBy === null || $takenBy === $sku) {
                break; // free, or already owned by this product
            }
            $attempt++;
            if ($attempt > 99) {
                return ['', self::S_CONFLICT];
            }
            $slug = $candidate . '-' . $attempt;
        }

        // Detect if the slug shadows another product's raw SKU.
        // Under the future three-step controller, url_slug lookup wins, so this
        // is a monitoring flag rather than a hard error.
        if (isset($allSkuSet[$slug]) && $slug !== $sku) {
            return [$slug, self::S_SKU_SHADOW];
        }

        $status = strlen($slug) < 5 ? self::S_NEEDS_REVIEW : self::S_NEW;

        return [$slug, $status];
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    /** Returns [slugPool (slug→sku), allSkuSet (sku→sku)]. */
    private function loadDbState(): array
    {
        $slugPool = DB::table('products')
            ->whereNotNull('url_slug')
            ->pluck('sku', 'url_slug')
            ->all();

        // keyed by sku for O(1) lookup
        $allSkuSet = DB::table('products')
            ->pluck('sku', 'sku')
            ->all();

        return [$slugPool, $allSkuSet];
    }

    // ── Output ────────────────────────────────────────────────────────────────

    private function printSummary(string $tag, array $stats, int $processed): void
    {
        $this->newLine();
        $this->info($tag . '──────────────────── Summary ────────────────────────');
        $this->line(sprintf('  Processed    : %d', $processed));
        $this->line(sprintf('  NEW          : %d  (written)', $stats[self::S_NEW]));
        $this->line(sprintf('  SKU_SHADOW   : %d  (written, slug overlaps another product\'s SKU)',
            $stats[self::S_SKU_SHADOW]));
        $this->line(sprintf('  NEEDS_REVIEW : %d  (written, slug is very short)',
            $stats[self::S_NEEDS_REVIEW]));
        $this->line(sprintf('  SKIP         : %d  (already had url_slug)', $stats[self::S_SKIP]));
        $this->line(sprintf('  CONFLICT     : %d  (NOT written — could not find unique slug)',
            $stats[self::S_CONFLICT]));
        $this->newLine();

        $writable = $stats[self::S_NEW] + $stats[self::S_SKU_SHADOW] + $stats[self::S_NEEDS_REVIEW];

        if ($this->dry) {
            $this->info("Would write {$writable} slug(s). Run without --dry-run to apply.");
        } elseif ($stats[self::S_CONFLICT] === 0) {
            $this->info("Written: {$writable}. Run again to confirm (should show 0 NEW).");
        } else {
            $this->error("{$stats[self::S_CONFLICT]} conflict(s) could not be resolved automatically.");
        }
    }
}
