<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_categories', function (Blueprint $table) {
            // SEO-friendly URL slug derived from the category name.
            // Written once by netbazar:generate-category-slugs; never touched by Kaspi imports.
            // NULL until the command is run; UNIQUE enforced at DB level.
            $table->string('url_slug', 255)
                  ->nullable()
                  ->unique()
                  ->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_categories', function (Blueprint $table) {
            $table->dropUnique(['url_slug']);
            $table->dropColumn('url_slug');
        });
    }
};
