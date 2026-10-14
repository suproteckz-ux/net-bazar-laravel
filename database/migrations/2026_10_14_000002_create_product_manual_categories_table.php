<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_manual_categories', function (Blueprint $table) {
            $table->string('sku');
            $table->unsignedBigInteger('manual_category_id');
            $table->integer('sort_order')->default(0);

            $table->primary(['sku', 'manual_category_id']);
            $table->index('manual_category_id');

            $table->foreign('sku')
                  ->references('sku')
                  ->on('products')
                  ->onDelete('cascade');

            $table->foreign('manual_category_id')
                  ->references('id')
                  ->on('manual_categories')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_manual_categories');
    }
};
