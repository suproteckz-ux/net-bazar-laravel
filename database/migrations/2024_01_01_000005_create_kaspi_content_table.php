<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_content', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->text('url');
            // TEXT/LONGTEXT columns cannot have DEFAULT in MySQL 8; application always supplies values
            $table->longText('description');
            $table->longText('attributes_json');
            $table->longText('photos_json');
            $table->longText('category_path_json');
            $table->longText('breadcrumbs_json');
            $table->string('status', 50);
            $table->text('detail');
            $table->string('source_sha256', 64)->default('');
            $table->timestamp('received_at')->useCurrent();
            // Manual overrides; never overwritten by enrichment pipeline
            $table->longText('manual_description')->nullable();
            $table->longText('manual_attributes_json')->nullable();
            $table->longText('manual_photos_json')->nullable();

            $table->foreign('sku')->references('sku')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_content');
    }
};
