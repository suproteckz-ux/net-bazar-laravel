<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_kaspi_categories', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->string('category_id')->index();

            $table->foreign('sku')->references('sku')->on('products');
            $table->foreign('category_id')->references('id')->on('kaspi_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_kaspi_categories');
    }
};
