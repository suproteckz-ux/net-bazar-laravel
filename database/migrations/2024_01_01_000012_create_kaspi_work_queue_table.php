<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_work_queue', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->integer('widget_pending');
            $table->integer('parser_pending');
            $table->text('reason');
            $table->string('queued_at', 50);

            $table->foreign('sku')->references('sku')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_work_queue');
    }
};
