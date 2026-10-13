<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'kompyuternye-i-ofisnye-kresla';

    public function up(): void
    {
        // ── 1. Map Satu 8531101 "Офисные кресла Алматы" ──────────────────────
        // Redirect goes directly to /catalog/kompyuternye-i-ofisnye-kresla.
        // Using target (not kaspi_id) because the subcategory ID is unknown without
        // the production DB; the slug is stable after generate-category-slugs ran.
        // If the import already wrote kaspi_id='6de353f47065ba6c7f96a205' (parent
        // "Офисные кресла"), this overwrite points to the more specific subcategory.
        DB::table('satu_category_redirects')->updateOrInsert(
            ['satu_id' => 8531101],
            [
                'kaspi_id'   => null,
                'target'     => '/catalog/' . self::SLUG,
                'confidence' => 'high',
            ]
        );

        // ── 2. SEO overrides for /catalog/kompyuternye-i-ofisnye-kresla ──────
        // Recovers ranking signals from the old Satu page "Офисные кресла в Алматы".
        // H1 and title carry the primary search term directly.
        $categoryId = DB::table('kaspi_categories')
            ->where('url_slug', self::SLUG)
            ->value('id');

        if ($categoryId === null) {
            return; // slug not generated yet — run generate-category-slugs first
        }

        $now    = now();
        $exists = DB::table('kaspi_category_overrides')
            ->where('category_id', $categoryId)
            ->exists();

        if ($exists) {
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
        DB::table('satu_category_redirects')->where('satu_id', 8531101)->delete();

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
