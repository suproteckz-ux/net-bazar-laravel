<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_batch_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->primary();
            $table->string('archived_at', 50);
            $table->text('table_name');
            $table->longText('payload_json');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_batch_archive');
    }
};
