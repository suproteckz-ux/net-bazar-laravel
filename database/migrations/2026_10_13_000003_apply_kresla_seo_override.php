<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Re-applies the kaspi_category_overrides entry for /catalog/kompyuternye-i-ofisnye-kresla.
//
// Migration 000002 inserted the satu_category_redirects row correctly but skipped
// the override because generate-category-slugs had not yet run at migration time
// (url_slug was NULL → early return). This migration runs after the slug exists.
return new class extends Migration
{
    private const SLUG = 'kompyuternye-i-ofisnye-kresla';

    public function up(): void
    {
        $categoryId = DB::table('kaspi_categories')
            ->where('url_slug', self::SLUG)
            ->value('id');

        if ($categoryId === null) {
            // Still no slug — run: php artisan netbazar:generate-category-slugs
            return;
        }

        $now    = now();
        $exists = DB::table('kaspi_category_overrides')
            ->where('category_id', $categoryId)
            ->exists();

        if ($exists) {
            // Only update the three SEO fields — leave display_name, seo_text,
            // display_order, custom_image untouched.
            DB::table('kaspi_category_overrides')
                ->where('category_id', $categoryId)
                ->update([
                    'h1'               => 'Офисные кресла в Алматы',
                    'seo_title'        => 'Офисные кресла в Алматы — купить с доставкой | NetBazar',
                    'meta_description' => 'Офисные кресла в Алматы — широкий выбор компьютерных и офисных кресел по доступным ценам. Быстрая доставка по Казахстану. Гарантия — NetBazar.',
                    'updated_at'       => $now,
                ]);
        } else {
            DB::table('kaspi_category_overrides')->insert([
                'category_id'      => $categoryId,
                'h1'               => 'Офисные кресла в Алматы',
                'seo_title'        => 'Офисные кресла в Алматы — купить с доставкой | NetBazar',
                'meta_description' => 'Офисные кресла в Алматы — широкий выбор компьютерных и офисных кресел по доступным ценам. Быстрая доставка по Казахстану. Гарантия — NetBazar.',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        $categoryId = DB::table('kaspi_categories')
            ->where('url_slug', self::SLUG)
            ->value('id');

        if ($categoryId !== null) {
            DB::table('kaspi_category_overrides')
                ->where('category_id', $categoryId)
                ->delete();
        }
    }
};
