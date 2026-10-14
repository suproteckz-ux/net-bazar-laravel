<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stores old kaspi category slugs so that /catalog/{old_slug} → 301 → /catalog/{new_slug}.
        // Populated manually (or by generate-category-slugs --force) before a slug is reassigned.
        // Intentionally no FK on kaspi_category_id: rebuild drops+recreates kaspi_categories, but
        // the same deterministic ID reappears after rebuild, so the mapping stays valid.
        Schema::create('kaspi_category_slug_redirects', function (Blueprint $table) {
            $table->string('old_slug', 255)->primary();
            $table->string('kaspi_category_id', 255)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_category_slug_redirects');
    }
};
