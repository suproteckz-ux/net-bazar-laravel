<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->text('source_offer_id');
            $table->text('name');
            $table->text('brand');
            // Stored as decimal string to preserve exact value from source feed
            $table->string('price_kzt', 50);
            $table->integer('quantity');
            $table->tinyInteger('available');
            $table->string('source_category_id')->index();
            $table->text('source_category_path');
            $table->longText('source_description');
            $table->longText('source_photos_json');
            $table->tinyInteger('present')->default(1)->index();
            // Legacy Kaspi columns (from old import_feed schema; kept for schema fidelity)
            $table->text('kaspi_url')->nullable();
            $table->longText('kaspi_category_path_json')->nullable();
            $table->longText('kaspi_description')->nullable();
            $table->longText('kaspi_attributes_json')->nullable();
            $table->longText('kaspi_photos_json')->nullable();
            $table->string('site_category_override')->nullable();
            // Editorial/manual overrides (never overwritten by imports)
            $table->longText('editorial_description')->nullable();
            $table->longText('editorial_photos_json')->nullable();
            $table->timestamp('last_imported_at')->useCurrent();

            $table->foreign('source_category_id')->references('id')->on('source_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
