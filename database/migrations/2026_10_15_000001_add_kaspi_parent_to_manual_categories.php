<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_categories', function (Blueprint $table) {
            // Intentionally NO foreign key constraint: RebuildKaspiCategories drops and
            // recreates kaspi_categories, which would cascade-null this column during the
            // delete phase if an ON DELETE SET NULL FK existed. Instead, the application
            // gracefully falls back to root-level display when the referenced ID is absent.
            $table->string('kaspi_parent_id', 255)->nullable()->after('parent_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('manual_categories', function (Blueprint $table) {
            $table->dropIndex(['kaspi_parent_id']);
            $table->dropColumn('kaspi_parent_id');
        });
    }
};
