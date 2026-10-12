<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildKaspiCategories extends Command
{
    protected $signature = 'netbazar:rebuild-kaspi-categories
                            {--dry-run : Show counts without making changes}
                            {--force   : Skip confirmation prompt}';

    protected $description = 'Rebuild kaspi_categories and product_kaspi_categories from existing kaspi_content data';

    public function handle(): int
    {
        $beforeCategories = DB::table('kaspi_categories')->count();
        $beforeMappings   = DB::table('product_kaspi_categories')->count();
        $beforeOverrides  = DB::table('kaspi_category_overrides')->count();

        $sourceProducts = DB::table('kaspi_content as e')
            ->join('kaspi_lookup as k', function ($join) {
                $join->on('k.sku', '=', 'e.sku')
                     ->on('k.url', '=', 'e.url')
                     ->where('k.status', 'resolved');
            })
            ->join('products as p', function ($join) {
                $join->on('p.sku', '=', 'e.sku')->where('p.present', 1);
            })
            ->where('e.status', 'ready')
            ->count();

        $this->line('Current state:');
        $this->line("  kaspi_categories         : {$beforeCategories}");
        $this->line("  product_kaspi_categories : {$beforeMappings}");
        $this->line("  kaspi_category_overrides : {$beforeOverrides}");
        $this->line('');
        $this->line("Source products for rebuild (ready + resolved + present): {$sourceProducts}");

        if ($this->option('dry-run')) {
            $this->info('[dry-run] No changes written.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm('Delete and rebuild kaspi_categories and product_kaspi_categories? Overrides with matching IDs will be restored.')) {
            $this->info('Aborted.');
            return 0;
        }

        $this->runRebuild($beforeOverrides > 0);

        $afterCategories = DB::table('kaspi_categories')->count();
        $afterMappings   = DB::table('product_kaspi_categories')->count();
        $afterOverrides  = DB::table('kaspi_category_overrides')->count();

        $this->info('Rebuild complete:');
        $this->line("  kaspi_categories         : {$beforeCategories} → {$afterCategories}");
        $this->line("  product_kaspi_categories : {$beforeMappings} → {$afterMappings}");
        $this->line("  kaspi_category_overrides : {$beforeOverrides} → {$afterOverrides} (preserved: {$afterOverrides})");

        return 0;
    }

    private function runRebuild(bool $loadOverrides): void
    {
        $overrides = $loadOverrides ? DB::table('kaspi_category_overrides')->get()->toArray() : [];

        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
            'sqlite'           => DB::statement('PRAGMA foreign_keys=OFF'),
            default            => null,
        };

        try {
            DB::table('kaspi_category_overrides')->delete();
            DB::table('product_kaspi_categories')->delete();
            DB::table('kaspi_categories')->delete();

            $rows = DB::table('kaspi_content as e')
                ->join('kaspi_lookup as k', function ($join) {
                    $join->on('k.sku', '=', 'e.sku')
                         ->on('k.url', '=', 'e.url')
                         ->where('k.status', 'resolved');
                })
                ->join('products as p', function ($join) {
                    $join->on('p.sku', '=', 'e.sku')->where('p.present', 1);
                })
                ->where('e.status', 'ready')
                ->select(['e.sku', 'e.category_path_json'])
                ->get();

            foreach ($rows as $row) {
                $path   = json_decode($row->category_path_json, true) ?? [];
                $names  = [];
                $parent = null;

                foreach ($path as $node) {
                    $names[] = $node['name'];
                    // Python json.dumps default separators use space after comma: ["A", "B"]
                    $json    = str_replace('","', '", "', json_encode($names, JSON_UNESCAPED_UNICODE));
                    $ident   = substr(hash('sha256', $json), 0, 24);
                    DB::table('kaspi_categories')->insertOrIgnore([
                        'id'        => $ident,
                        'parent_id' => $parent,
                        'name'      => $node['name'],
                        'path'      => implode(' / ', $names),
                        'source_id' => $node['id'],
                    ]);
                    $parent = $ident;
                }

                if ($parent !== null) {
                    DB::table('product_kaspi_categories')->insert([
                        'sku'         => $row->sku,
                        'category_id' => $parent,
                    ]);
                }
            }
        } finally {
            match (DB::getDriverName()) {
                'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS=1'),
                'sqlite'           => DB::statement('PRAGMA foreign_keys=ON'),
                default            => null,
            };
        }

        if ($overrides) {
            $existingIds = DB::table('kaspi_categories')->pluck('id')->flip()->toArray();
            foreach ($overrides as $override) {
                if (isset($existingIds[$override->category_id])) {
                    DB::table('kaspi_category_overrides')->insertOrIgnore((array) $override);
                }
            }
        }
    }
}
