<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->primary();
            $table->timestamp('imported_at')->useCurrent();
            $table->string('sha256', 64);
            $table->integer('product_count');
            $table->integer('category_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
