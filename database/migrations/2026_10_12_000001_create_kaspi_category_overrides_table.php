<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_category_overrides', function (Blueprint $table) {
            $table->string('category_id')->primary();
            $table->string('display_name', 255)->nullable();
            $table->integer('display_order')->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('h1', 255)->nullable();
            $table->text('seo_text')->nullable();
            $table->string('custom_image', 500)->nullable();
            $table->timestamps();

            $table->foreign('category_id')
                  ->references('id')
                  ->on('kaspi_categories')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_category_overrides');
    }
};
