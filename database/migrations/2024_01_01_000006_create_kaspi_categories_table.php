<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-referential: parent_id FK added after table creation
        Schema::create('kaspi_categories', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('parent_id')->nullable()->index();
            $table->text('name');
            $table->text('path');
            $table->text('source_id');
        });

        Schema::table('kaspi_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('kaspi_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_categories');
    }
};
