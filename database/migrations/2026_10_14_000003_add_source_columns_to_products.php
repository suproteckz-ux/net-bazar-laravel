<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('source', 10)->default('feed')->after('last_imported_at');
            $table->boolean('exclude_from_feed_sync')->default(false)->after('source');
        });

        // Seed sentinel rows so FK on source_category_id is always satisfiable.
        // __manual__             — used for admin-created products
        // __export_no_category__ — used by CatalogImport when a feed SKU has no category
        DB::table('source_categories')->insertOrIgnore([
            ['id' => '__manual__',             'name' => 'Ручной товар',         'path' => '__manual__',             'present' => 0],
            ['id' => '__export_no_category__', 'name' => 'Без категории (фид)',  'path' => '__export_no_category__', 'present' => 0],
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['source', 'exclude_from_feed_sync']);
        });

        DB::table('source_categories')
            ->whereIn('id', ['__manual__', '__export_no_category__'])
            ->delete();
    }
};
