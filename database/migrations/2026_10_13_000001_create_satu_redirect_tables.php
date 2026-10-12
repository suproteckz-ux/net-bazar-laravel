<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maps Satu product ID → the product's current SKU in the `products` table.
        // The controller resolves url_slug at request time (fresh DB lookup) so
        // no redirect chains form when url_slugs are generated after import.
        Schema::create('satu_product_redirects', function (Blueprint $table) {
            $table->unsignedBigInteger('satu_id')->primary();
            $table->string('sku', 255);       // products.sku of the resolved product
            $table->index('sku');
        });

        // Maps Satu group ID → kaspi_categories.id (primary) or a fallback target path.
        // Controller resolves url_slug from kaspi_categories at request time.
        Schema::create('satu_category_redirects', function (Blueprint $table) {
            $table->unsignedBigInteger('satu_id')->primary();
            $table->string('kaspi_id', 100)->nullable();   // kaspi_categories.id for dynamic lookup
            $table->string('target', 500)->nullable();     // fallback: /catalog/... or /catalog?category=...
            $table->string('confidence', 20)->default('high');
            $table->index('kaspi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_category_redirects');
        Schema::dropIfExists('satu_product_redirects');
    }
};
