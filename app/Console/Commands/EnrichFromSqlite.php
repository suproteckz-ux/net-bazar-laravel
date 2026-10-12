<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

/**
 * Safely merge Kaspi enrichment results from local SQLite into MySQL.
 *
 * Rules (enforced unconditionally):
 *   – No TRUNCATE, no DELETE, no full table overwrites.
 *   – Only kaspi_lookup and kaspi_content are written to.
 *   – Only updates products that already exist in MySQL products table.
 *   – Never downgrades kaspi_lookup.status from 'resolved' to 'not_found'.
 *   – Never overwrites kaspi_content.manual_* columns.
 *   – Never writes empty/null over existing non-empty values.
 *   – Idempotent: same source data → same result on repeated runs.
 *   – Products, prices, stock, orders, users — never touched.
 */
class EnrichFromSqlite extends Command
{
    protected $signature = 'netbazar:enrich-from-sqlite
        {--source=   : Path to SQLite file (overrides SQLITE_SOURCE in .env)}
        {--dry-run   : Show what would change; no DB writes}
        {--limit=0   : Process at most N SKUs per stage (0 = no limit)}
        {--sku=*     : Process only these SKUs (repeatable)}
        {--batch=100 : SKUs per DB transaction}';

    protected $description = 'Merge Kaspi enrichment (kaspi_lookup + kaspi_content) from SQLite → MySQL, no truncate';

    /** JSON values that are considered "empty" — never overwrite real data with these */
    private const EMPTY_JSON = ['', '[]', 'null'];

    private bool $dry;

    private array $st = [
        'lookup'  => ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0],
        'content' => ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $source = $this->option('source') ?: env('SQLITE_SOURCE');
        if (!$source || !file_exists($source)) {
            $this->error('SQLite not found: ' . ($source ?: 'SQLITE_SOURCE is not set in .env'));
            return 1;
        }

        $this->dry   = (bool)  $this->option('dry-run');
        $limit       = (int)   $this->option('limit');
        $skuFilter   = (array) $this->option('sku');
        $batchSize   = max(1, (int) $this->option('batch'));
        $tag         = $this->dry ? '[DRY-RUN] ' : '';

        $this->info("{$tag}Source : {$source}");
        $this->info("{$tag}Batch  : {$batchSize} | Limit: " . ($limit ?: 'none') .
                    ($skuFilter ? ' | SKUs: ' . implode(', ', $skuFilter) : ''));

        try {
            $sqlite = new PDO('sqlite:' . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $e) {
            $this->error('Cannot open SQLite: ' . $e->getMessage());
            return 1;
        }

        // Pre-load MySQL state once to avoid N+1 queries
        $this->line('');
        $this->line('Loading MySQL state…');
        $mProducts = DB::table('products')->pluck('sku', 'sku')->all();
        $mLookupAll = DB::table('kaspi_lookup')
            ->get(['sku', 'url', 'status', 'attempts', 'frame_url'])
            ->keyBy('sku')
            ->all();
        $mContent = DB::table('kaspi_content')
            ->get()
            ->keyBy('sku')
            ->all();

        $this->line(sprintf(
            '  MySQL: products=%d  kaspi_lookup=%d  kaspi_content=%d',
            count($mProducts), count($mLookupAll), count($mContent)
        ));

        // ── Stage 1: kaspi_lookup ────────────────────────────────────────────
        $this->line('');
        $this->line('[1/2] kaspi_lookup (resolved entries only)…');
        $this->stageLookup($sqlite, $mProducts, $mLookupAll, $skuFilter, $limit, $batchSize);

        // Reload resolved lookup after stage 1 writes (in non-dry mode, new rows are now there)
        $mResolvedLookup = DB::table('kaspi_lookup')
            ->where('status', 'resolved')
            ->get(['sku', 'url'])
            ->keyBy('sku')
            ->all();

        // In dry-run, stage-1 writes didn't happen. Build an augmented map from what stage 1
        // *would* have created, so stage-2 counts are more accurate.
        if ($this->dry) {
            $srcLookupResolved = $this->fetchSqlite($sqlite, 'kaspi_lookup', "status='resolved'", $skuFilter, $limit);
            foreach ($srcLookupResolved as $row) {
                $sku = $row['sku'];
                if (!isset($mResolvedLookup[$sku]) && isset($mProducts[$sku])) {
                    $mResolvedLookup[$sku] = (object)['sku' => $sku, 'url' => $row['url']];
                }
            }
        }

        // ── Stage 2: kaspi_content ───────────────────────────────────────────
        $this->line('');
        $this->line('[2/2] kaspi_content (ready entries only)…');
        $this->stageContent($sqlite, $mProducts, $mResolvedLookup, $mContent, $skuFilter, $limit, $batchSize);

        $this->printSummary($tag);

        return ($this->st['lookup']['errors'] + $this->st['content']['errors']) > 0 ? 1 : 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stage 1 — kaspi_lookup
    // ─────────────────────────────────────────────────────────────────────────

    private function stageLookup(PDO $sqlite, array $mProducts, array $mLookupAll,
                                   array $skuFilter, int $limit, int $batchSize): void
    {
        $rows = $this->fetchSqlite($sqlite, 'kaspi_lookup', "status='resolved'", $skuFilter, $limit);
        $this->line('  SQLite resolved : ' . count($rows));

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            $toInsert = [];
            $toUpdate = [];

            foreach ($chunk as $src) {
                $sku = $src['sku'];

                // Requirement 3: only update existing MySQL products
                if (!isset($mProducts[$sku])) {
                    $this->st['lookup']['skipped']++;
                    continue;
                }

                $dst = $mLookupAll[$sku] ?? null;

                if ($dst === null) {
                    // New entry — insert
                    $toInsert[] = $src;
                } elseif ($dst->status === 'resolved' && $dst->url === $src['url']) {
                    // Same URL already resolved — nothing to do
                    $this->st['lookup']['unchanged']++;
                } else {
                    // Either: non-resolved in MySQL (upgrade), or URL changed
                    // Requirement 4: we NEVER set not_found here (src is always resolved)
                    $toUpdate[] = ['src' => $src, 'dst' => $dst];
                }
            }

            $this->writeLookupBatch($toInsert, $toUpdate);
        }

        $lk = $this->st['lookup'];
        $this->line(sprintf(
            '  created=%-5d updated=%-5d unchanged=%-5d skipped=%-5d errors=%d',
            $lk['created'], $lk['updated'], $lk['unchanged'], $lk['skipped'], $lk['errors']
        ));
    }

    private function writeLookupBatch(array $toInsert, array $toUpdate): void
    {
        if ($this->dry) {
            $this->st['lookup']['created'] += count($toInsert);
            $this->st['lookup']['updated']  += count($toUpdate);
            return;
        }

        if (empty($toInsert) && empty($toUpdate)) {
            return;
        }

        try {
            DB::transaction(function () use ($toInsert, $toUpdate) {
                foreach ($toInsert as $src) {
                    DB::table('kaspi_lookup')->insert([
                        'sku'        => $src['sku'],
                        'partner_id' => $src['partner_id'] ?? 'Avtoximiya',
                        'city_id'    => $src['city_id']    ?? '750000000',
                        'status'     => $src['status'],
                        'url'        => $src['url'],
                        'checked_at' => $src['checked_at'] ?? now(),
                        'detail'     => $src['detail']     ?? '',
                        'frame_url'  => $src['frame_url']  ?? '',
                        'attempts'   => $src['attempts']   ?? 1,
                    ]);
                    $this->st['lookup']['created']++;
                }

                foreach ($toUpdate as ['src' => $src, 'dst' => $dst]) {
                    $update = [
                        'status'     => 'resolved',
                        'url'        => $src['url'],
                        'checked_at' => $src['checked_at'] ?? now(),
                        'detail'     => $src['detail']     ?? '',
                        // Keep highest attempt count
                        'attempts'   => max((int)($dst->attempts ?? 0), (int)($src['attempts'] ?? 1)),
                    ];
                    // Only set frame_url if it's currently empty in MySQL
                    if (empty($dst->frame_url ?? '') && !empty($src['frame_url'])) {
                        $update['frame_url'] = $src['frame_url'];
                    }
                    DB::table('kaspi_lookup')->where('sku', $src['sku'])->update($update);
                    $this->st['lookup']['updated']++;
                }
            });
        } catch (Throwable $e) {
            $count = count($toInsert) + count($toUpdate);
            $this->logBatchError('kaspi_lookup', $count, $e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stage 2 — kaspi_content
    // ─────────────────────────────────────────────────────────────────────────

    private function stageContent(PDO $sqlite, array $mProducts, array $mResolvedLookup,
                                    array $mContent, array $skuFilter, int $limit, int $batchSize): void
    {
        $rows = $this->fetchSqlite($sqlite, 'kaspi_content', "status='ready'", $skuFilter, $limit);
        $this->line('  SQLite ready    : ' . count($rows));

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            $toInsert = [];
            $toUpdate = [];

            foreach ($chunk as $src) {
                $sku = $src['sku'];

                // Requirement 3
                if (!isset($mProducts[$sku])) {
                    $this->st['content']['skipped']++;
                    continue;
                }

                $lookupRow = $mResolvedLookup[$sku] ?? null;
                if (!$lookupRow) {
                    // No confirmed URL in MySQL → skip (content without URL is useless)
                    $this->st['content']['skipped']++;
                    continue;
                }

                $dst = $mContent[$sku] ?? null;

                if ($dst === null) {
                    // New content row
                    $toInsert[] = ['src' => $src, 'url' => $lookupRow->url];
                } else {
                    // Compare only allowed fields
                    $diff = $this->diffContent((array)$dst, $src, $lookupRow->url);
                    if (!empty($diff)) {
                        $toUpdate[] = ['sku' => $sku, 'diff' => $diff];
                    } else {
                        $this->st['content']['unchanged']++;
                    }
                }
            }

            $this->writeContentBatch($toInsert, $toUpdate);
        }

        $ct = $this->st['content'];
        $this->line(sprintf(
            '  created=%-5d updated=%-5d unchanged=%-5d skipped=%-5d errors=%d',
            $ct['created'], $ct['updated'], $ct['unchanged'], $ct['skipped'], $ct['errors']
        ));
    }

    private function writeContentBatch(array $toInsert, array $toUpdate): void
    {
        if ($this->dry) {
            $this->st['content']['created'] += count($toInsert);
            $this->st['content']['updated']  += count($toUpdate);
            return;
        }

        if (empty($toInsert) && empty($toUpdate)) {
            return;
        }

        try {
            DB::transaction(function () use ($toInsert, $toUpdate) {
                foreach ($toInsert as ['src' => $src, 'url' => $url]) {
                    DB::table('kaspi_content')->insert([
                        'sku'                => $src['sku'],
                        'url'                => $url,
                        'description'        => $src['description']        ?? '',
                        'attributes_json'    => $src['attributes_json']    ?? '[]',
                        'photos_json'        => $src['photos_json']        ?? '[]',
                        'category_path_json' => $src['category_path_json'] ?? '[]',
                        'breadcrumbs_json'   => $src['breadcrumbs_json']   ?? '[]',
                        'status'             => 'ready',
                        'detail'             => $src['detail']             ?? '',
                        'source_sha256'      => $src['source_sha256']      ?? '',
                        'received_at'        => $src['received_at']        ?? now(),
                        // manual_* intentionally omitted → default NULL
                    ]);
                    $this->st['content']['created']++;
                }

                foreach ($toUpdate as ['sku' => $sku, 'diff' => $diff]) {
                    DB::table('kaspi_content')->where('sku', $sku)->update($diff);
                    $this->st['content']['updated']++;
                }
            });
        } catch (Throwable $e) {
            $count = count($toInsert) + count($toUpdate);
            $this->logBatchError('kaspi_content', $count, $e);
        }
    }

    /**
     * Compute the set of fields to UPDATE for an existing kaspi_content row.
     *
     * Returns [] when nothing should change.
     *
     * Safety rules applied here:
     *  - manual_* columns are NEVER included (requirement 6).
     *  - Empty/null source values never overwrite existing non-empty values (requirement 5).
     *  - status only upgraded to 'ready', never downgraded.
     *  - URL comes from MySQL kaspi_lookup (authoritative), not from SQLite kaspi_content.
     */
    private function diffContent(array $dst, array $src, string $mysqlUrl): array
    {
        // Fast path: matching SHA-256 + same URL → content is identical, no write needed
        if (
            !empty($src['source_sha256']) &&
            ($src['source_sha256'] === ($dst['source_sha256'] ?? '')) &&
            ($mysqlUrl === ($dst['url'] ?? ''))
        ) {
            return [];
        }

        $diff = [];

        // URL: authoritative from MySQL kaspi_lookup
        if ($mysqlUrl !== ($dst['url'] ?? '')) {
            $diff['url'] = $mysqlUrl;
        }

        // Plain text fields: only if source is non-empty and differs
        foreach (['description', 'detail', 'source_sha256'] as $field) {
            $srcVal = $src[$field] ?? '';
            $dstVal = $dst[$field] ?? '';
            if ($srcVal !== '' && $srcVal !== $dstVal) {
                $diff[$field] = $srcVal;
            }
        }

        // JSON fields: only if source is non-empty JSON and differs
        foreach (['attributes_json', 'photos_json', 'category_path_json', 'breadcrumbs_json'] as $field) {
            $srcVal = $src[$field] ?? '[]';
            $dstVal = $dst[$field] ?? '[]';
            if (!in_array($srcVal, self::EMPTY_JSON, true) && $srcVal !== $dstVal) {
                $diff[$field] = $srcVal;
            }
        }

        // Status: only upgrade to 'ready', never downgrade
        if (($dst['status'] ?? '') !== 'ready' && ($src['status'] ?? '') === 'ready') {
            $diff['status'] = 'ready';
        }

        // Timestamp: update only when actual content changed (not just URL)
        if (!empty($diff)) {
            $diff['received_at'] = now();
        }

        return $diff;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch rows from a SQLite table with an optional WHERE clause, SKU filter and row limit.
     */
    private function fetchSqlite(PDO $sqlite, string $table, string $where,
                                   array $skuFilter, int $limit): array
    {
        $sql    = "SELECT * FROM \"{$table}\" WHERE {$where}";
        $params = [];

        if ($skuFilter) {
            $placeholders = implode(',', array_fill(0, count($skuFilter), '?'));
            $sql         .= " AND sku IN ({$placeholders})";
            $params       = $skuFilter;
        }

        $sql .= ' ORDER BY sku';

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $sqlite->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logBatchError(string $stage, int $count, Throwable $e): void
    {
        $msg = "[{$stage}] batch of {$count} rows failed: " . $e->getMessage();
        $this->error($msg);
        Log::error($msg, ['trace' => $e->getTraceAsString()]);

        $key = str_replace('kaspi_', '', $stage);
        if (isset($this->st[$key])) {
            $this->st[$key]['errors'] += $count;
        }
    }

    private function printSummary(string $tag): void
    {
        $lk = $this->st['lookup'];
        $ct = $this->st['content'];

        $this->line('');
        $this->info($tag . '──────────────────────────── Summary ────────────────────────────────');
        $this->line(sprintf(
            '  kaspi_lookup  : created=%-5d updated=%-5d unchanged=%-5d skipped=%-5d errors=%d',
            $lk['created'], $lk['updated'], $lk['unchanged'], $lk['skipped'], $lk['errors']
        ));
        $this->line(sprintf(
            '  kaspi_content : created=%-5d updated=%-5d unchanged=%-5d skipped=%-5d errors=%d',
            $ct['created'], $ct['updated'], $ct['unchanged'], $ct['skipped'], $ct['errors']
        ));
        $this->line('');

        $totalWrites = $lk['created'] + $lk['updated'] + $ct['created'] + $ct['updated'];
        $totalErrors = $lk['errors']  + $ct['errors'];

        if ($this->dry) {
            $this->info("  Would perform {$totalWrites} write(s). Nothing was written.");
            $this->comment('  Note: content skipped counts may be slightly under-counted in dry-run');
            $this->comment('  if some kaspi_lookup rows would be created in stage 1.');
        } else {
            if ($totalErrors > 0) {
                $this->warn("  Completed with {$totalErrors} error(s). See laravel.log for details.");
            } else {
                $this->info("  All {$totalWrites} write(s) committed successfully.");
            }
            $this->line('');
            $this->comment('  ⚠ New products will appear in product pages but NOT in category');
            $this->comment('    navigation until product_kaspi_categories is updated for them.');
        }
    }
}
