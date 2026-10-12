<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic: audits Satu product URLs against the live Laravel products table.
 *
 * No writes to any table. No external HTTP requests. No catalog changes.
 * Safe to run on production via Plesk / Laravel Toolkit.
 */
class AuditSatuMapping extends Command
{
    protected $signature = 'seo:audit-satu-mapping
        {--csv=    : Path to product_mapping.csv (default: seo-migration/reports/product_mapping.csv)}
        {--output= : Output CSV path (default: seo-migration/reports/audit_{timestamp}.csv)}
        {--stats   : Print statistics only; skip writing the output CSV}';

    protected $description = 'Audit Satu → Laravel product URL mapping (read-only, no DB writes)';

    // ── Status constants ──────────────────────────────────────────────────────

    const EXACT_MATCH = 'EXACT_MATCH';   // SKU found in products, present=1
    const ALIAS_MATCH = 'ALIAS_MATCH';   // Found via catalog_sku_aliases, present=1
    const INACTIVE    = 'INACTIVE';      // SKU in products but present=0
    const NOT_PRESENT = 'NOT_PRESENT';   // Alias resolves to a product with present=0
    const NOT_FOUND   = 'NOT_FOUND';     // Not in products or aliases
    const AMBIGUOUS   = 'AMBIGUOUS';     // Case-insensitive normalization yields multiple hits
    const NO_SKU      = 'NO_SKU';        // Source row has no SKU

    // ── Entry point ───────────────────────────────────────────────────────────

    public function handle(): int
    {
        $csvPath = $this->option('csv')
            ?: base_path('seo-migration/reports/product_mapping.csv');

        if (!file_exists($csvPath)) {
            $this->error("Source CSV not found: {$csvPath}");
            return 1;
        }

        $this->line("Source: {$csvPath}");

        // Bulk-load DB state once — no N+1 queries.
        $this->line('Loading DB state…');
        [$products, $productsCiMap, $aliases, $aliasesCiMap] = $this->loadDbState();

        $conflicts = count(array_filter($productsCiMap, fn($v) => count($v) > 1))
                   + count(array_filter($aliasesCiMap,  fn($v) => count($v) > 1));

        $this->line(sprintf(
            '  products=%d  aliases=%d  ci_conflicts=%d',
            count($products), count($aliases), $conflicts
        ));

        // Parse and audit each CSV row.
        $this->line('Auditing rows…');
        $rows = $this->parseCsv($csvPath);
        $this->line('  rows: ' . count($rows));

        $results = [];
        $stats   = array_fill_keys([
            self::EXACT_MATCH, self::ALIAS_MATCH, self::NOT_FOUND,
            self::AMBIGUOUS,   self::INACTIVE,    self::NOT_PRESENT, self::NO_SKU,
        ], 0);

        foreach ($rows as $row) {
            $result          = $this->auditRow($row, $products, $productsCiMap, $aliases, $aliasesCiMap);
            $results[]       = $result;
            $stats[$result['status']]++;
        }

        $this->printStats($stats, count($rows));

        if (!$this->option('stats')) {
            $outPath = $this->option('output')
                ?: base_path('seo-migration/reports/audit_' . date('Y-m-d_H-i-s') . '.csv');

            $this->writeCsv($outPath, $results);
            $this->info("Report: {$outPath}");
        }

        return 0;
    }

    // ── DB loading ────────────────────────────────────────────────────────────

    /** Returns [products map, products ci-map, aliases map, aliases ci-map] */
    private function loadDbState(): array
    {
        $products = DB::table('products')
            ->get(['sku', 'present', 'available'])
            ->keyBy('sku')
            ->all();

        // lowercase(sku) → [sku, ...]  — detects case-insensitive conflicts
        $productsCiMap = [];
        foreach ($products as $sku => $_) {
            $productsCiMap[mb_strtolower($sku)][] = $sku;
        }

        $aliases = DB::table('catalog_sku_aliases')
            ->get(['old_sku', 'new_sku'])
            ->pluck('new_sku', 'old_sku')
            ->all();

        // lowercase(old_sku) → [{old, new}, ...]
        $aliasesCiMap = [];
        foreach ($aliases as $oldSku => $newSku) {
            $aliasesCiMap[mb_strtolower($oldSku)][] = ['old' => $oldSku, 'new' => $newSku];
        }

        return [$products, $productsCiMap, $aliases, $aliasesCiMap];
    }

    // ── Row audit ─────────────────────────────────────────────────────────────

    private function auditRow(
        array $row,
        array $products,
        array $productsCiMap,
        array $aliases,
        array $aliasesCiMap
    ): array {
        $csvSku = trim($row['sku'] ?? '');

        $out = [
            'old_url'       => $row['path']    ?? '',
            'satu_id'       => $row['satu_id'] ?? '',
            'original_sku'  => $csvSku,
            'actual_sku'    => '',
            'present'       => '',
            'available'     => '',
            'status'        => '',
            'reason'        => '',
            'candidate_url' => '',
        ];

        if ($csvSku === '') {
            $out['status'] = self::NO_SKU;
            $out['reason'] = 'No SKU in source export';
            return $out;
        }

        // 1 ── Exact product match (case-sensitive)
        if (isset($products[$csvSku])) {
            return $this->fillFromProduct($out, $csvSku, $products[$csvSku], 'Exact match');
        }

        // 2 ── Exact alias match
        if (isset($aliases[$csvSku])) {
            return $this->fillFromAlias($out, $csvSku, $aliases[$csvSku], $products, 'Exact alias');
        }

        // 3 ── Case-insensitive product match
        $lowerSku     = mb_strtolower($csvSku);
        $ciCandidates = $productsCiMap[$lowerSku] ?? [];

        if (count($ciCandidates) > 1) {
            $out['status'] = self::AMBIGUOUS;
            $out['reason'] = 'Multiple products match case-insensitively: ' . implode(', ', $ciCandidates);
            return $out;
        }

        if (count($ciCandidates) === 1) {
            $actual = $ciCandidates[0];
            return $this->fillFromProduct($out, $actual, $products[$actual], "Case-insensitive match → {$actual}");
        }

        // 4 ── Case-insensitive alias match
        $ciAliasCandidates = $aliasesCiMap[$lowerSku] ?? [];

        if (count($ciAliasCandidates) > 1) {
            $out['status'] = self::AMBIGUOUS;
            $out['reason'] = 'Multiple aliases match case-insensitively';
            return $out;
        }

        if (count($ciAliasCandidates) === 1) {
            $entry  = $ciAliasCandidates[0];
            $reason = "Case-insensitive alias → {$entry['new']}";
            return $this->fillFromAlias($out, $entry['old'], $entry['new'], $products, $reason);
        }

        // 5 ── Nothing found
        $out['status'] = self::NOT_FOUND;
        $out['reason'] = 'SKU not found in products or aliases';
        return $out;
    }

    private function fillFromProduct(array $out, string $actualSku, object $p, string $matchNote): array
    {
        $out['actual_sku']  = $actualSku;
        $out['present']     = (int) $p->present;
        $out['available']   = (int) $p->available;
        $out['candidate_url'] = '/product/' . rawurlencode($actualSku);

        if ((int) $p->present === 1) {
            $out['status'] = self::EXACT_MATCH;
            $out['reason'] = "{$matchNote}, present=1";
        } else {
            $out['status'] = self::INACTIVE;
            $out['reason'] = "{$matchNote}, present=0 (removed from feed)";
        }

        return $out;
    }

    private function fillFromAlias(array $out, string $oldSku, string $newSku, array $products, string $note): array
    {
        $out['actual_sku'] = $newSku;

        if (!isset($products[$newSku])) {
            $out['status'] = self::NOT_FOUND;
            $out['reason'] = "{$note}: alias target '{$newSku}' not in products";
            return $out;
        }

        $p = $products[$newSku];
        $out['present']       = (int) $p->present;
        $out['available']     = (int) $p->available;
        $out['candidate_url'] = '/product/' . rawurlencode($newSku);

        if ((int) $p->present === 1) {
            $out['status'] = self::ALIAS_MATCH;
            $out['reason'] = "{$note}, present=1";
        } else {
            $out['status'] = self::NOT_PRESENT;
            $out['reason'] = "{$note}, present=0 (alias target removed from feed)";
        }

        return $out;
    }

    // ── CSV I/O ───────────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open: {$path}");
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }
        $header   = array_map('trim', $header);
        $colCount = count($header);
        $rows     = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < $colCount) {
                $data = array_pad($data, $colCount, '');
            }
            $rows[] = array_combine($header, array_slice($data, 0, $colCount));
        }

        fclose($handle);
        return $rows;
    }

    private function writeCsv(string $path, array $results): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Cannot write: {$path}");
        }

        fputcsv($handle, [
            'old_url', 'satu_id', 'original_sku', 'actual_sku',
            'present', 'available', 'status', 'reason', 'candidate_url',
        ]);

        foreach ($results as $row) {
            fputcsv($handle, [
                $row['old_url'],
                $row['satu_id'],
                $row['original_sku'],
                $row['actual_sku'],
                $row['present'],
                $row['available'],
                $row['status'],
                $row['reason'],
                $row['candidate_url'],
            ]);
        }

        fclose($handle);
    }

    // ── Display ───────────────────────────────────────────────────────────────

    private function printStats(array $stats, int $total): void
    {
        $this->newLine();
        $this->info('──────────────────── Audit Summary ───────────────────────');
        $this->line(sprintf('  Total rows: %d', $total));
        $this->newLine();

        $labels = [
            self::EXACT_MATCH => 'Exact match (present=1)',
            self::ALIAS_MATCH => 'Alias match (present=1)',
            self::INACTIVE    => 'Found but present=0',
            self::NOT_PRESENT => 'Alias target present=0',
            self::NOT_FOUND   => 'Not found anywhere',
            self::AMBIGUOUS   => 'Ambiguous (case conflicts)',
            self::NO_SKU      => 'No SKU in source',
        ];

        foreach ($labels as $status => $label) {
            $count = $stats[$status];
            $pct   = $total > 0 ? round($count / $total * 100, 1) : 0;
            $this->line(sprintf('  %-13s %-22s : %4d  (%5.1f%%)', $status, $label, $count, $pct));
        }

        $redirectable = $stats[self::EXACT_MATCH] + $stats[self::ALIAS_MATCH];
        $this->newLine();
        $this->line(sprintf('  Ready for 301 redirect : %d / %d', $redirectable, $total));
        $this->newLine();
    }
}
