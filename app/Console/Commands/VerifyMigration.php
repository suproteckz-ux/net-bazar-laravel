<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class VerifyMigration extends Command
{
    protected $signature = 'netbazar:verify
        {--source= : Path to source SQLite (overrides SQLITE_SOURCE env)}';

    protected $description = 'Verify row counts and data integrity after SQLite → MySQL import';

    /** Spot-check SKUs known to have rich Kaspi content */
    private const SPOT_SKUS = ['002B', '00000000680', '00000000024', 'КА-00012984'];

    public function handle(): int
    {
        $source = $this->option('source') ?: env('SQLITE_SOURCE');
        $hasSqlite = $source && file_exists($source);

        if ($hasSqlite) {
            $sqlite = new PDO('sqlite:' . $source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->info("Comparing MySQL ← SQLite: {$source}");
        } else {
            $this->info('No SQLite source — verifying MySQL only');
            $sqlite = null;
        }

        $pass = true;

        // ── Row count comparison ────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=yellow>Row counts:</>');

        $tables = [
            'source_categories', 'products', 'import_runs',
            'kaspi_lookup', 'kaspi_content', 'kaspi_categories',
            'product_kaspi_categories', 'stock_pp3', 'stock_pp3_runs',
            'catalog_sync_runs', 'catalog_sku_aliases', 'kaspi_work_queue',
            'kaspi_batch_archive', 'batch_attempts', 'batch_meta',
        ];

        foreach ($tables as $table) {
            $mysql = DB::table($table)->count();
            $line = sprintf('  %-40s MySQL: %5d', $table, $mysql);

            if ($sqlite) {
                try {
                    $sq = (int) $sqlite->query("SELECT count(*) FROM \"{$table}\"")->fetchColumn();
                    $match = $mysql === $sq;
                    $line .= sprintf('  SQLite: %5d  %s', $sq, $match ? '✓' : '✗ MISMATCH');
                    if (!$match) {
                        $pass = false;
                    }
                } catch (\Exception $e) {
                    $line .= '  SQLite: table not found';
                }
            }
            $this->line($line);
        }

        // ── Key business metrics ────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=yellow>Business metrics:</>');

        $presentProducts = DB::table('products')->where('present', 1)->count();
        $this->line("  Present products (present=1):       {$presentProducts}");
        if ($presentProducts < 3000) {
            $this->error("  ✗ Expected ≥3000 present products, got {$presentProducts}");
            $pass = false;
        }

        $resolved = DB::table('kaspi_lookup')->where('status', 'resolved')->count();
        $this->line("  Resolved Kaspi lookups:             {$resolved}");

        $enriched = DB::table('kaspi_content')->where('status', 'ready')->count();
        $this->line("  Enriched (status=ready) products:   {$enriched}");

        $categories = DB::table('kaspi_categories')->count();
        $this->line("  Kaspi categories:                   {$categories}");

        $aliases = DB::table('catalog_sku_aliases')->count();
        $this->line("  SKU aliases:                        {$aliases}");

        $inStock = DB::table('stock_pp3')->where('state', 'in_stock')->count();
        $preorder = DB::table('stock_pp3')->where('state', 'preorder')->count();
        $outOfStock = DB::table('stock_pp3')->where('state', 'out_of_stock')->count();
        $this->line("  Stock states — in_stock: {$inStock}, preorder: {$preorder}, out_of_stock: {$outOfStock}");

        // ── Spot-check representative SKUs ──────────────────────────────────
        $this->newLine();
        $this->line('<fg=yellow>Spot-checks:</>');

        foreach (self::SPOT_SKUS as $sku) {
            $product = DB::table('products')->where('sku', $sku)->first();
            if (!$product) {
                $this->line("  {$sku}: not in MySQL (may be absent from this catalog)");
                continue;
            }

            $lookup = DB::table('kaspi_lookup')->where('sku', $sku)->first();
            $content = DB::table('kaspi_content')->where('sku', $sku)->first();
            $stock = DB::table('stock_pp3')->where('sku', $sku)->first();

            $this->line(sprintf(
                "  %-20s  price=%-12s  present=%d  kaspi=%s  content=%s  stock=%s",
                $sku,
                $product->price_kzt,
                $product->present,
                $lookup ? $lookup->status : 'none',
                $content ? $content->status : 'none',
                $stock ? $stock->state : 'none'
            ));

            // Verify prices are numeric strings (not null, not empty)
            if (!is_numeric($product->price_kzt)) {
                $this->error("  ✗ {$sku}: price_kzt is not numeric: '{$product->price_kzt}'");
                $pass = false;
            }

            // Verify photo JSON round-trips
            if ($content) {
                $photos = json_decode($content->photos_json, true);
                if ($photos === null) {
                    $this->error("  ✗ {$sku}: photos_json is not valid JSON");
                    $pass = false;
                }
            }

            // Verify SQLite spot-check values match MySQL
            if ($sqlite) {
                $sqProd = $sqlite->prepare("SELECT price_kzt, name, brand FROM products WHERE sku = ?");
                $sqProd->execute([$sku]);
                $sqRow = $sqProd->fetch(PDO::FETCH_ASSOC);
                if ($sqRow) {
                    if ($sqRow['price_kzt'] !== $product->price_kzt) {
                        $this->error("  ✗ {$sku}: price_kzt mismatch. SQLite='{$sqRow['price_kzt']}' MySQL='{$product->price_kzt}'");
                        $pass = false;
                    }
                    if ($sqRow['name'] !== $product->name) {
                        $this->error("  ✗ {$sku}: name mismatch");
                        $pass = false;
                    }
                }
            }
        }

        // ── MySQL FK integrity check ────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=yellow>Foreign key integrity:</>');

        // Check for orphaned rows in key junction tables
        $orphanLookup = DB::table('kaspi_lookup as kl')
            ->leftJoin('products as p', 'p.sku', '=', 'kl.sku')
            ->whereNull('p.sku')
            ->count();
        $orphanContent = DB::table('kaspi_content as kc')
            ->leftJoin('products as p', 'p.sku', '=', 'kc.sku')
            ->whereNull('p.sku')
            ->count();
        $orphanStock = DB::table('stock_pp3 as s')
            ->leftJoin('products as p', 'p.sku', '=', 's.sku')
            ->whereNull('p.sku')
            ->count();
        $orphanQueue = DB::table('kaspi_work_queue as q')
            ->leftJoin('products as p', 'p.sku', '=', 'q.sku')
            ->whereNull('p.sku')
            ->count();

        foreach ([
            'kaspi_lookup → products' => $orphanLookup,
            'kaspi_content → products' => $orphanContent,
            'stock_pp3 → products' => $orphanStock,
            'kaspi_work_queue → products' => $orphanQueue,
        ] as $label => $orphans) {
            $icon = $orphans === 0 ? '✓' : '✗';
            $this->line("  {$icon}  {$label}: {$orphans} orphans");
            if ($orphans > 0) {
                $pass = false;
            }
        }

        // ── Result ──────────────────────────────────────────────────────────
        $this->newLine();
        if ($pass) {
            $this->info('All checks passed.');
        } else {
            $this->error('One or more checks FAILED — review output above.');
        }

        return $pass ? 0 : 1;
    }
}
