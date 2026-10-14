<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_tiles', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->string('image_path', 500)->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->string('link_type', 20)->default('kaspi_category');
            $table->string('kaspi_category_id', 100)->nullable();
            $table->unsignedBigInteger('manual_category_id')->nullable();
            $table->string('custom_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('seed_key', 50)->nullable()->unique();
            $table->timestamps();

            $table->foreign('manual_category_id')
                  ->references('id')
                  ->on('manual_categories')
                  ->nullOnDelete();

            $table->index(['is_active', 'sort_order']);
        });

        // Seed the 5 original home tiles from config.
        // seed_key ensures idempotency — re-running migrations never creates duplicates.
        DB::table('home_tiles')->insertOrIgnore([
            [
                'label'             => 'Автотовары',
                'image_path'        => '/images/categories/auto.png',
                'image_alt'         => 'Автотовары',
                'link_type'         => 'kaspi_category',
                'kaspi_category_id' => '6bede016037a3d7e79338836',
                'sort_order'        => 1,
                'is_active'         => 1,
                'seed_key'          => 'legacy_auto',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'label'             => 'Кресла',
                'image_path'        => '/images/categories/chairs.png',
                'image_alt'         => 'Кресла',
                'link_type'         => 'kaspi_category',
                'kaspi_category_id' => '8d536ca215bb111d3f040ae0',
                'sort_order'        => 2,
                'is_active'         => 1,
                'seed_key'          => 'legacy_chairs',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'label'             => 'Товары для дома',
                'image_path'        => '/images/categories/coolers.png',
                'image_alt'         => 'Товары для дома',
                'link_type'         => 'kaspi_category',
                'kaspi_category_id' => 'd71c72bf8bd183bc8aca5a01',
                'sort_order'        => 3,
                'is_active'         => 1,
                'seed_key'          => 'legacy_home',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'label'             => 'Красота и здоровье',
                'image_path'        => '/images/categories/chem.png',
                'image_alt'         => 'Красота и здоровье',
                'link_type'         => 'kaspi_category',
                'kaspi_category_id' => '978f1bd96be215ea108babf8',
                'sort_order'        => 4,
                'is_active'         => 1,
                'seed_key'          => 'legacy_beauty',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'label'             => 'Бытовая техника',
                'image_path'        => '/images/categories/tech.png',
                'image_alt'         => 'Бытовая техника',
                'link_type'         => 'kaspi_category',
                'kaspi_category_id' => '34e9b5d0f75a8dd2a4437425',
                'sort_order'        => 5,
                'is_active'         => 1,
                'seed_key'          => 'legacy_tech',
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('home_tiles');
    }
};
