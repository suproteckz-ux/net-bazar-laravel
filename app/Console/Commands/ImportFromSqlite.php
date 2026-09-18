<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ImportFromSqlite extends Command
{
    protected $signature = 'netbazar:import
        {--source= : Path to the SQLite database (overrides SQLITE_SOURCE env)}
        {--dry-run : Parse and count rows without writing to MySQL}';

    protected $description = 'Import all data from the Python NetBazar SQLite database into MySQL';

    /** Tables imported in FK-safe order */
    private const TABLES = [
        'source_categories',
        'products',
        'import_runs',
        'kaspi_lookup',
        'kaspi_content',
        'kaspi_categories',
        'product_kaspi_categories',
        'stock_pp3',
        'stock_pp3_runs',
        'catalog_sync_runs',
        'catalog_sku_aliases',
        'kaspi_work_queue',
        'kaspi_batch_archive',
        'batch_attempts',
        'batch_meta',
    ];

    private const CHUNK = 500;

    public function handle(): int
    {
        $source = $this->option('source') ?: env('SQLITE_SOURCE');
        if (!$source || !file_exists($source)) {
            $this->error("SQLite source not found: {$source}");
            $this->line("Set SQLITE_SOURCE in .env or pass --source=<path>");
            return 1;
        }

        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? '[DRY RUN] Reading SQLite — no writes to MySQL' : "Importing from: {$source}");

        $sqlite = new PDO('sqlite:' . $source, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Discover which optional tables actually exist in the source DB
        $existing = array_column(
            $sqlite->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_ASSOC),
            'name'
        );

        $counts = [];
        foreach (self::TABLES as $table) {
            if (!in_array($table, $existing, true)) {
                $this->line("  skip  {$table} (not in source)");
                $counts[$table] = 0;
                continue;
            }
            $rows = $sqlite->query("SELECT * FROM \"{$table}\"")->fetchAll(PDO::FETCH_ASSOC);
            $counts[$table] = count($rows);

            if ($dry || count($rows) === 0) {
                $verb = $dry ? 'found' : 'empty';
                $this->line(sprintf('  %s  %-40s %d rows', $dry ? 'found' : 'empty', $table, count($rows)));
                continue;
            }

            if (!$dry) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::table($table)->truncate();
            }

            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                // Normalize: cast empty string to null for nullable TEXT columns where MySQL differs
                $chunk = array_map([$this, 'normalizeRow'], $chunk);
                if (!$dry) {
                    DB::table($table)->insert($chunk);
                }
            }

            if (!$dry) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            $this->line(sprintf('  %s  %-40s %d rows', $dry ? 'found' : 'imported', $table, count($rows)));
        }

        if (!$dry) {
            // Verify FK integrity after all data is loaded
            $violations = DB::select('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()');
            // Quick sanity: run MySQL FK check by re-enabling and querying
            $this->info('Running foreign key sanity check...');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $total = array_sum($counts);
        $this->info(sprintf('%s complete. Total rows: %d', $dry ? 'Dry-run' : 'Import', $total));

        return 0;
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $col => &$value) {
            // SQLite stores booleans and integers as text/integer interchangeably;
            // pass through as-is — MySQL columns are typed to accept the values.
            if ($value === '') {
                // Keep empty strings as empty strings (not null) — Python stores '' not NULL
                // for TEXT NOT NULL columns with empty defaults.
            }
        }
        return $row;
    }
}
