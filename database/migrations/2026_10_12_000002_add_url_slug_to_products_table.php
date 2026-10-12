<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // SEO-friendly URL slug derived from the full product name.
            // Written once by netbazar:generate-url-slugs; never touched by catalog imports.
            // NULL until the command is run; UNIQUE enforced at DB level.
            $table->string('url_slug', 255)
                  ->nullable()
                  ->unique()
                  ->after('site_category_override');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['url_slug']);
            $table->dropColumn('url_slug');
        });
    }
};
