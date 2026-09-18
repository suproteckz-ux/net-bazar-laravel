<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_sync_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->primary();
            $table->string('time', 50);
            $table->string('sha256', 64);
            $table->integer('offer_count');
            $table->longText('report_json');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_sync_runs');
    }
};
