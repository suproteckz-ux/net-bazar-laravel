<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->primary();
            $table->string('stage', 50);
            $table->string('sku');
            $table->text('url')->nullable();
            $table->string('started_at', 50);
            $table->string('finished_at', 50)->nullable();
            $table->string('status', 50);
            $table->text('detail');

            $table->index(['sku', 'stage'], 'batch_attempts_sku_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_attempts');
    }
};
