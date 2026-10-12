<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditSatuMappingTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'seo_audit_') . '.csv';
        $fh   = fopen($path, 'w');
        fputcsv($fh, ['satu_id', 'path', 'slug', 'sku', 'name', 'mfr', 'grp_num', 'grp_name', 'status', 'db_status', 'target_url', 'note']);
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
        return $path;
    }

    private function makeOutputPath(): string
    {
        return tempnam(sys_get_temp_dir(), 'seo_out_') . '.csv';
    }

    private function seedProduct(string $sku, int $present = 1, int $available = 1): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'sc-audit-test', 'name' => 'Test', 'path' => 'Test', 'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => "Product {$sku}",
            'brand'                => 'B',
            'price_kzt'            => '100',
            'quantity'             => 1,
            'available'            => $available,
            'source_category_id'   => 'sc-audit-test',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => $present,
            'last_imported_at'     => now(),
        ]);
    }

    private function seedAlias(string $oldSku, string $newSku): void
    {
        DB::table('catalog_sku_aliases')->insertOrIgnore([
            'old_sku'    => $oldSku,
            'new_sku'    => $newSku,
            'migrated_at'=> '2026-01-01',
        ]);
    }

    /** Parse the output CSV into an array of associative rows keyed by satu_id. */
    private function parseOutput(string $path): array
    {
        $fh   = fopen($path, 'r');
        $hdr  = fgetcsv($fh);
        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            $row           = array_combine($hdr, $data);
            $rows[$row['satu_id']] = $row;
        }
        fclose($fh);
        return $rows;
    }

    // ─── Individual status tests ──────────────────────────────────────────────

    public function test_no_sku_row_is_classified_as_no_sku(): void
    {
        $csv = $this->makeCsv([
            ['111', '/p111-item.html', 'item', '', 'Item with no sku', '', '9999', 'Cat', 'NO_SKU', '', '', 'Satu row has no SKU'],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('NO_SKU',  $rows['111']['status']);
        $this->assertSame('',        $rows['111']['actual_sku']);
        $this->assertSame('',        $rows['111']['candidate_url']);
    }

    public function test_exact_match_active_product(): void
    {
        $this->seedProduct('LN2137');

        $csv = $this->makeCsv([
            ['104372776', '/p104372776-lavr-ln2137.html', 'lavr-ln2137', 'LN2137', 'Lavr LN2137', 'Lavr', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('EXACT_MATCH',       $rows['104372776']['status']);
        $this->assertSame('LN2137',             $rows['104372776']['actual_sku']);
        $this->assertSame('1',                  $rows['104372776']['present']);
        $this->assertSame('/product/LN2137',    $rows['104372776']['candidate_url']);
    }

    public function test_product_with_present_zero_is_classified_as_inactive(): void
    {
        $this->seedProduct('OLD-SKU', present: 0);

        $csv = $this->makeCsv([
            ['200', '/p200-item.html', 'item', 'OLD-SKU', 'Old Item', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('INACTIVE',            $rows['200']['status']);
        $this->assertSame('OLD-SKU',             $rows['200']['actual_sku']);
        $this->assertSame('0',                   $rows['200']['present']);
        $this->assertSame('/product/OLD-SKU',    $rows['200']['candidate_url']);
    }

    public function test_alias_match_resolves_to_active_product(): void
    {
        $this->seedProduct('NEW-SKU-123');
        $this->seedAlias('OLD-SKU-123', 'NEW-SKU-123');

        $csv = $this->makeCsv([
            ['300', '/p300-item.html', 'item', 'OLD-SKU-123', 'Renamed product', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('ALIAS_MATCH',            $rows['300']['status']);
        $this->assertSame('NEW-SKU-123',            $rows['300']['actual_sku']);
        $this->assertSame('1',                      $rows['300']['present']);
        $this->assertSame('/product/NEW-SKU-123',   $rows['300']['candidate_url']);
    }

    public function test_alias_pointing_to_inactive_product_is_not_present(): void
    {
        $this->seedProduct('INACTIVE-TARGET', present: 0);
        $this->seedAlias('SATU-SKU-X', 'INACTIVE-TARGET');

        $csv = $this->makeCsv([
            ['400', '/p400-item.html', 'item', 'SATU-SKU-X', 'Item X', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('NOT_PRESENT',             $rows['400']['status']);
        $this->assertSame('INACTIVE-TARGET',         $rows['400']['actual_sku']);
        $this->assertSame('0',                       $rows['400']['present']);
        $this->assertSame('/product/INACTIVE-TARGET', $rows['400']['candidate_url']);
    }

    public function test_not_found_when_sku_absent_from_db(): void
    {
        // No seeding — DB is empty (RefreshDatabase)
        $csv = $this->makeCsv([
            ['500', '/p500-ghost.html', 'ghost', 'GHOST-SKU', 'Ghost', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('NOT_FOUND', $rows['500']['status']);
        $this->assertSame('',          $rows['500']['actual_sku']);
        $this->assertSame('',          $rows['500']['candidate_url']);
    }

    public function test_case_insensitive_match_finds_product(): void
    {
        // Product stored as lowercase in DB; Satu exports it uppercase
        $this->seedProduct('ln2137lower');

        $csv = $this->makeCsv([
            ['600', '/p600-item.html', 'item', 'LN2137LOWER', 'Case variant', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('EXACT_MATCH',            $rows['600']['status']);
        $this->assertSame('ln2137lower',            $rows['600']['actual_sku']);
        $this->assertSame('/product/ln2137lower',   $rows['600']['candidate_url']);
    }

    public function test_ambiguous_when_multiple_case_insensitive_products(): void
    {
        // Two products that differ only in case (can happen with case-sensitive collation)
        $this->seedProduct('sku-dupe');
        $this->seedProduct('SKU-DUPE');

        $csv = $this->makeCsv([
            ['700', '/p700-item.html', 'item', 'Sku-Dupe', 'Ambiguous', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('AMBIGUOUS', $rows['700']['status']);
        $this->assertSame('',          $rows['700']['candidate_url']);
    }

    public function test_sku_with_spaces_produces_encoded_candidate_url(): void
    {
        $this->seedProduct('GS BLOC GS 900 WHITE');

        $csv = $this->makeCsv([
            ['800', '/p800-gs-bloc.html', 'gs-bloc', 'GS BLOC GS 900 WHITE', 'GS BLOC', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('EXACT_MATCH',                           $rows['800']['status']);
        $this->assertSame('/product/GS%20BLOC%20GS%20900%20WHITE', $rows['800']['candidate_url']);
    }

    public function test_alias_target_missing_from_products_is_not_found(): void
    {
        // Alias exists but the new_sku was never imported
        $this->seedAlias('ORPHAN-OLD', 'ORPHAN-NEW');
        // ORPHAN-NEW not in products

        $csv = $this->makeCsv([
            ['900', '/p900-orphan.html', 'orphan', 'ORPHAN-OLD', 'Orphan', '', '9999', 'Cat', 'MATCHED', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertSame('NOT_FOUND', $rows['900']['status']);
        $this->assertSame('ORPHAN-NEW', $rows['900']['actual_sku']);
    }

    // ─── Statistics and output structure ─────────────────────────────────────

    public function test_statistics_are_counted_correctly(): void
    {
        $this->seedProduct('ACTIVE-1');
        $this->seedProduct('INACTIVE-1', present: 0);
        $this->seedProduct('ALIAS-NEW');
        $this->seedAlias('ALIAS-OLD', 'ALIAS-NEW');

        $csv = $this->makeCsv([
            ['1', '/p1.html', 's', 'ACTIVE-1',  'A', '', '0', 'C', 'MATCHED', '', '', ''],  // EXACT_MATCH
            ['2', '/p2.html', 's', 'INACTIVE-1','A', '', '0', 'C', 'MATCHED', '', '', ''],  // INACTIVE
            ['3', '/p3.html', 's', 'ALIAS-OLD', 'A', '', '0', 'C', 'MATCHED', '', '', ''],  // ALIAS_MATCH
            ['4', '/p4.html', 's', 'MISSING',   'A', '', '0', 'C', 'MATCHED', '', '', ''],  // NOT_FOUND
            ['5', '/p5.html', 's', '',          'A', '', '0', 'C', 'NO_SKU',  '', '', ''],  // NO_SKU
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful()
            ->expectsOutputToContain('EXACT_MATCH')
            ->expectsOutputToContain('ALIAS_MATCH')
            ->expectsOutputToContain('NOT_FOUND')
            ->expectsOutputToContain('NO_SKU');

        $rows = $this->parseOutput($out);
        $this->assertCount(5, $rows);

        $statusCounts = array_count_values(array_column($rows, 'status'));
        $this->assertSame(1, $statusCounts['EXACT_MATCH']);
        $this->assertSame(1, $statusCounts['INACTIVE']);
        $this->assertSame(1, $statusCounts['ALIAS_MATCH']);
        $this->assertSame(1, $statusCounts['NOT_FOUND']);
        $this->assertSame(1, $statusCounts['NO_SKU']);
    }

    public function test_output_csv_has_correct_columns(): void
    {
        $csv = $this->makeCsv([
            ['99', '/p99.html', 's', '', 'No sku', '', '0', 'C', 'NO_SKU', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out])
            ->assertSuccessful();

        $fh  = fopen($out, 'r');
        $hdr = fgetcsv($fh);
        fclose($fh);

        $expected = ['old_url', 'satu_id', 'original_sku', 'actual_sku',
                     'present', 'available', 'status', 'reason', 'candidate_url'];
        $this->assertSame($expected, $hdr);
    }

    public function test_stats_only_flag_skips_output_file(): void
    {
        $csv = $this->makeCsv([
            ['1', '/p1.html', 's', 'X', 'X', '', '0', 'C', 'NO_SKU', '', '', ''],
        ]);
        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $csv, '--output' => $out, '--stats' => true])
            ->assertSuccessful();

        // --stats suppresses file writing; file should not exist (we passed a path but flag wins)
        $this->assertFileDoesNotExist($out);
    }

    public function test_missing_csv_returns_error(): void
    {
        $this->artisan('seo:audit-satu-mapping', ['--csv' => '/nonexistent/path.csv'])
            ->assertFailed();
    }

    // ─── satu_id extraction ───────────────────────────────────────────────────

    /**
     * Reproduces the production bug: the source CSV has a UTF-8 BOM which
     * corrupts the first column header to "\xEF\xBB\xBFsatu_id", making
     * $row['satu_id'] return null. The fix extracts the ID from the path.
     */
    public function test_satu_id_extracted_from_path_when_column_is_bom_corrupted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'seo_bom_') . '.csv';
        $fh   = fopen($path, 'wb');

        // Write UTF-8 BOM before the header — exactly what Python's csv writer
        // produces on Windows when the file is opened with encoding='utf-8-sig'.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['satu_id', 'path', 'slug', 'sku', 'name', 'mfr', 'grp_num', 'grp_name', 'status', 'db_status', 'target_url', 'note']);
        fputcsv($fh, ['104372827', '/p104372827-vstraivaemyj-morozilnik.html', 'slug', '', 'Name', '', '0', 'C', 'NO_SKU', '', '', '']);
        fclose($fh);

        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $path, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        // BOM is stripped → column 'satu_id' is correctly read as '104372827'
        $this->assertArrayHasKey('104372827', $rows, 'satu_id must be keyed by 104372827');
        $this->assertSame('104372827', $rows['104372827']['satu_id']);

        unlink($path);
    }

    public function test_satu_id_extracted_from_path_when_column_is_empty(): void
    {
        // Simulate a CSV where satu_id column exists but all values are empty
        // (e.g. a different export format). The path always contains the ID.
        $path = tempnam(sys_get_temp_dir(), 'seo_noid_') . '.csv';
        $fh   = fopen($path, 'w');
        fputcsv($fh, ['satu_id', 'path', 'slug', 'sku', 'name', 'mfr', 'grp_num', 'grp_name', 'status', 'db_status', 'target_url', 'note']);
        fputcsv($fh, ['', '/p987654321-some-product.html', 'slug', '', 'Name', '', '0', 'C', 'NO_SKU', '', '', '']);
        fclose($fh);

        $out = $this->makeOutputPath();

        $this->artisan('seo:audit-satu-mapping', ['--csv' => $path, '--output' => $out])
            ->assertSuccessful();

        $rows = $this->parseOutput($out);
        $this->assertArrayHasKey('987654321', $rows, 'satu_id must be extracted from path');
        $this->assertSame('987654321', $rows['987654321']['satu_id']);

        unlink($path);
    }
}
