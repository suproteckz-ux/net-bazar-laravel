<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_lookup', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->string('partner_id');
            $table->string('city_id', 50);
            $table->string('status', 50);
            // NULL when status != 'resolved'; enforced by CHECK constraint below
            $table->text('url')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->text('detail');
            $table->text('frame_url');
            $table->integer('attempts')->default(1);

            $table->foreign('sku')->references('sku')->on('products');
        });

        // MySQL: enforce (resolved AND url NOT NULL) OR (not resolved AND url IS NULL)
        // SQLite: CHECK in ALTER TABLE is not supported; constraint is skipped (tested via app logic)
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement(
                "ALTER TABLE kaspi_lookup ADD CONSTRAINT kaspi_lookup_status_url_check
                 CHECK (
                     (status = 'resolved' AND url IS NOT NULL)
                     OR (status <> 'resolved' AND url IS NULL)
                 )"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_lookup');
    }
};
