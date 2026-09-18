<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_sku_aliases', function (Blueprint $table) {
            $table->string('old_sku')->primary();
            $table->string('new_sku')->index();
            $table->string('migrated_at', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_sku_aliases');
    }
};
