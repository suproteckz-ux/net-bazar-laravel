<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import Satu.kz URL mappings into satu_product_redirects and satu_category_redirects.
 *
 * Reads:
 *   {csv-dir}/product_mapping.csv   — 3802 rows from the Satu audit
 *   {csv-dir}/category_candidates.csv — 94 category group rows
 *
 * Products:   resolves SKU via products table (EXACT_MATCH) or catalog_sku_aliases (ALIAS_MATCH)
 * Categories: resolves kaspi_categories by name; only high+medium confidence rows
 *
 * Safe to re-run: use --force to overwrite existing rows.
 * Does NOT redirect NOT_FOUND products to the home page or assign mass 410s.
 *
 * Usage (production, after migrations are run):
 *   php artisan netbazar:import-satu-redirects --dry-run
 *   php artisan netbazar:import-satu-redirects
 */
class ImportSatuRedirects extends Command
{
    protected $signature = 'netbazar:import-satu-redirects
        {--dry-run         : Show what would be imported; make no DB writes}
        {--force           : Re-import and overwrite existing rows}
        {--products-only   : Skip category CSV}
        {--categories-only : Skip product CSV}
        {--csv-dir=        : Directory with CSVs (default: seo-migration/reports)}';

    protected $description = 'Import Satu.kz legacy URL mappings for 301 redirects (idempotent, no mass 410s)';

    private bool $dry;

    public function handle(): int
    {
        $this->dry   = (bool) $this->option('dry-run');
        $force       = (bool) $this->option('force');
        $csvDir      = $this->option('csv-dir') ?: base_path('seo-migration/reports');
        $tag         = $this->dry ? '[DRY-RUN] ' : '';

        $this->info("{$tag}netbazar:import-satu-redirects");
        $this->line("  csv-dir: {$csvDir}");

        $errors = 0;

        if (!$this->option('categories-only')) {
            $errors += $this->importProducts($csvDir, $force);
        }
        if (!$this->option('products-only')) {
            $errors += $this->importCategories($csvDir, $force);
        }

        return $errors > 0 ? 1 : 0;
    }

    // ── Product import ────────────────────────────────────────────────────────

    private function importProducts(string $csvDir, bool $force): int
    {
        $csvPath = $csvDir . '/product_mapping.csv';
        if (!file_exists($csvPath)) {
            $this->error("Product mapping CSV not found: {$csvPath}");
            return 1;
        }

        $rows = $this->readCsv($csvPath);
        $this->newLine();
        $this->line("── Products ──────────────────────────────────────────────");
        $this->line("  CSV rows: " . count($rows));

        $hasAliases = Schema::hasTable('catalog_sku_aliases');

        $counts   = ['EXACT_MATCH' => 0, 'ALIAS_MATCH' => 0, 'NOT_FOUND' => 0, 'NO_SKU' => 0, 'SKIPPED' => 0];
        $toInsert = [];

        foreach ($rows as $row) {
            $satuId = (int) ($row['satu_id'] ?? 0);
            $sku    = trim($row['sku'] ?? '');
            $status = trim($row['status'] ?? '');

            if ($status === 'NO_SKU' || $sku === '') {
                $counts['NO_SKU']++;
                continue;
            }

            if (!$force) {
                if (DB::table('satu_product_redirects')->where('satu_id', $satuId)->exists()) {
                    $counts['SKIPPED']++;
                    continue;
                }
            }

            // EXACT_MATCH: product exists with this SKU directly in products table
            $product = DB::table('products')->where('sku', $sku)->select(['url_slug', 'sku'])->first();
            if ($product !== null) {
                $toInsert[] = ['satu_id' => $satuId, 'sku' => $sku];
                $counts['EXACT_MATCH']++;
                continue;
            }

            // ALIAS_MATCH: old SKU was renamed — follow the alias to the current product
            if ($hasAliases) {
                $newSku = DB::table('catalog_sku_aliases')->where('old_sku', $sku)->value('new_sku');
                if ($newSku !== null) {
                    $aliasProduct = DB::table('products')->where('sku', $newSku)->exists();
                    if ($aliasProduct) {
                        $toInsert[] = ['satu_id' => $satuId, 'sku' => $newSku];
                        $counts['ALIAS_MATCH']++;
                        continue;
                    }
                }
            }

            // NOT_FOUND: no product with this SKU — do NOT redirect to home or assign 410
            $counts['NOT_FOUND']++;
        }

        $written = 0;
        if (!$this->dry) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                if ($force) {
                    foreach ($chunk as $r) {
                        DB::table('satu_product_redirects')->updateOrInsert(
                            ['satu_id' => $r['satu_id']],
                            ['sku' => $r['sku']]
                        );
                    }
                } else {
                    DB::table('satu_product_redirects')->insertOrIgnore($chunk);
                }
                $written += count($chunk);
            }
        } else {
            $written = count($toInsert);
            foreach (array_slice($toInsert, 0, 5) as $r) {
                $this->line(sprintf('  /p%d-... → /product/%s', $r['satu_id'], $r['sku']));
            }
            if (count($toInsert) > 5) {
                $this->line('  ... (showing 5 of ' . count($toInsert) . ')');
            }
        }

        $this->newLine();
        $this->info(
            "  EXACT_MATCH={$counts['EXACT_MATCH']}"
            . " | ALIAS_MATCH={$counts['ALIAS_MATCH']}"
            . " | NOT_FOUND={$counts['NOT_FOUND']}"
            . " | NO_SKU={$counts['NO_SKU']}"
            . " | SKIPPED={$counts['SKIPPED']}"
        );
        $this->info("  Ready to write: {$written}  (dry-run=" . ($this->dry ? 'yes' : 'no') . ")");

        return 0;
    }

    // ── Category import ───────────────────────────────────────────────────────

    private function importCategories(string $csvDir, bool $force): int
    {
        $csvPath = $csvDir . '/category_candidates.csv';
        if (!file_exists($csvPath)) {
            $this->error("Category CSV not found: {$csvPath}");
            return 1;
        }

        $rows = $this->readCsv($csvPath);
        $this->newLine();
        $this->line("── Categories ────────────────────────────────────────────");
        $this->line("  CSV rows: " . count($rows));

        $counts   = ['MAPPED' => 0, 'NOT_FOUND' => 0, 'SKIPPED_LOW' => 0, 'SKIPPED' => 0];
        $toInsert = [];

        foreach ($rows as $row) {
            $satuId     = (int) ($row['satu_id'] ?? 0);
            $confidence = trim($row['confidence'] ?? 'low');
            $candidate  = trim($row['laravel_candidate'] ?? '');

            // Only high + medium confidence rows; skip NEEDS_MANUAL_CHECK / NO_MATCH
            if ($confidence === 'low'
                || in_array($candidate, ['NEEDS_MANUAL_CHECK', 'NO_MATCH', ''], true)
            ) {
                $counts['SKIPPED_LOW']++;
                continue;
            }

            if (!str_starts_with($candidate, 'Kaspi: ')) {
                $counts['SKIPPED_LOW']++;
                continue;
            }

            if (!$force) {
                if (DB::table('satu_category_redirects')->where('satu_id', $satuId)->exists()) {
                    $counts['SKIPPED']++;
                    continue;
                }
            }

            $kaspiName = substr($candidate, 7); // strip "Kaspi: "
            $kaspiId   = $this->resolveKaspiId($kaspiName);

            if ($kaspiId !== null) {
                $toInsert[] = [
                    'satu_id'    => $satuId,
                    'kaspi_id'   => $kaspiId,
                    'target'     => null,
                    'confidence' => $confidence,
                ];
                $counts['MAPPED']++;
            } else {
                $this->warn("  [NOT_FOUND] /g{$satuId} → «{$kaspiName}»");
                $counts['NOT_FOUND']++;
            }
        }

        $written = 0;
        if (!$this->dry) {
            foreach ($toInsert as $r) {
                if ($force) {
                    DB::table('satu_category_redirects')->updateOrInsert(
                        ['satu_id' => $r['satu_id']],
                        ['kaspi_id' => $r['kaspi_id'], 'target' => $r['target'], 'confidence' => $r['confidence']]
                    );
                } else {
                    DB::table('satu_category_redirects')->insertOrIgnore($r);
                }
            }
            $written = count($toInsert);
        } else {
            $written = count($toInsert);
            foreach ($toInsert as $r) {
                $this->line(sprintf('  /g%d-... → kaspi_id=%s', $r['satu_id'], $r['kaspi_id']));
            }
        }

        $this->newLine();
        $this->info(
            "  MAPPED={$counts['MAPPED']}"
            . " | NOT_FOUND={$counts['NOT_FOUND']}"
            . " | SKIPPED_LOW={$counts['SKIPPED_LOW']}"
            . " | SKIPPED={$counts['SKIPPED']}"
        );
        $this->info("  Ready to write: {$written}  (dry-run=" . ($this->dry ? 'yes' : 'no') . ")");

        return 0;
    }

    /**
     * Resolve a kaspi category display name to its ID in kaspi_categories.
     * Handles: "X/Y" candidates (try each part), parenthetical subcategory notes.
     */
    private function resolveKaspiId(string $name): ?string
    {
        // "Автохимия/Автотовары" → try "Автохимия" then "Автотовары"
        if (str_contains($name, '/')) {
            foreach (explode('/', $name) as $part) {
                $id = $this->resolveKaspiId(trim($part));
                if ($id !== null) {
                    return $id;
                }
            }
            return null;
        }

        // "Офисные кресла (посетители)" → try exact, then stripped
        $clean = trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $name));

        // Exact match on cleaned name
        $cat = DB::table('kaspi_categories')->where('name', $clean)->value('id');
        if ($cat !== null) {
            return $cat;
        }

        // Exact match on original (with parentheses)
        if ($clean !== $name) {
            $cat = DB::table('kaspi_categories')->where('name', $name)->value('id');
            if ($cat !== null) {
                return $cat;
            }
        }

        // LIKE fallback (case-insensitive prefix search)
        $cat = DB::table('kaspi_categories')
            ->where('name', 'LIKE', $clean . '%')
            ->orderBy('path')   // prefer shallower (root) categories
            ->value('id');
        if ($cat !== null) {
            $this->line("  [LIKE_MATCH] '{$clean}%' → id={$cat}");
            return $cat;
        }

        return null;
    }

    // ── CSV reader ────────────────────────────────────────────────────────────

    /** Read a UTF-8-BOM CSV file, return array of associative rows. */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = null;
        $rows    = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                // Strip UTF-8 BOM from first header field if present
                $headers = array_map(fn($h) => ltrim($h, "\xEF\xBB\xBF\xfe\xff"), $line);
                continue;
            }
            if (count($line) < count($headers)) {
                $line = array_pad($line, count($headers), '');
            }
            $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
        }

        fclose($handle);
        return $rows;
    }
}
