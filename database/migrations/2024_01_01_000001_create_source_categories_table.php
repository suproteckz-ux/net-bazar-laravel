<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_categories', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('parent_id')->nullable()->index();
            $table->text('name');
            $table->text('path');
            $table->tinyInteger('present')->default(1);

            $table->foreign('parent_id')->references('id')->on('source_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_categories');
    }
};
