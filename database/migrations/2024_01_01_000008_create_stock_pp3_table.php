<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_pp3', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->string('export_sku')->nullable();
            $table->string('match_method', 50);
            $table->integer('raw_stock');
            $table->integer('preorder_days');
            $table->integer('source_available');
            $table->integer('quantity');
            $table->string('state', 20);
            $table->integer('present_export');
            // Stored as ISO 8601 text to match Python output exactly
            $table->string('updated_at', 50);

            $table->foreign('sku')->references('sku')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_pp3');
    }
};
