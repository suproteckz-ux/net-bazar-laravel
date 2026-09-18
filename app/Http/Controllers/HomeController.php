<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    private const CATEGORIES = [
        [
            'name'     => 'Бытовая техника',
            'slug'     => 'bytovaya-tehnika',
            'patterns' => ['%Бытов%', '%техник%', '%Стиральн%', '%Холодильник%', '%Посудомо%'],
        ],
        [
            'name'     => 'Кресла',
            'slug'     => 'kresla',
            'patterns' => ['%Кресл%', '%Офисн%кресл%'],
        ],
        [
            'name'     => 'Кулеры для воды',
            'slug'     => 'kulery-dlya-vody',
            'patterns' => ['%Кулер%', '%Помп%'],
        ],
        [
            'name'     => 'Химия',
            'slug'     => 'himiya',
            'patterns' => ['%Хими%', '%Автохими%', '%Средств%уборк%'],
        ],
        [
            'name'     => 'Автоаксессуары',
            'slug'     => 'avtoaksessuary',
            'patterns' => ['%Масла и технич%', '%Моторн%масл%', '%Трансмисс%'],
        ],
    ];

    public function index()
    {
        $categories = [];

        foreach (self::CATEGORIES as $cat) {
            $photo = $this->findCategoryPhoto($cat['patterns'], $cat['slug']);
            $categories[] = [
                'name'  => $cat['name'],
                'slug'  => $cat['slug'],
                'photo' => $photo,
            ];
        }

        // Hero collage: check public/images/hero/collage.{webp,jpg,png}
        $heroImage = null;
        foreach (['webp', 'jpg', 'png'] as $ext) {
            if (file_exists(public_path("images/hero/collage.{$ext}"))) {
                $heroImage = "/images/hero/collage.{$ext}";
                break;
            }
        }

        return view('home', compact('categories', 'heroImage'));
    }

    private function findCategoryPhoto(array $patterns, string $slug = ''): ?string
    {
        // 1. Check for a dedicated presentation asset in public/images/categories/
        //    Filenames: {slug}.webp, {slug}.jpg, {slug}.png
        if ($slug !== '') {
            foreach (['webp', 'jpg', 'png'] as $ext) {
                $filePath = public_path("images/categories/{$slug}.{$ext}");
                if (file_exists($filePath)) {
                    return "/images/categories/{$slug}.{$ext}";
                }
            }
        }

        // 2. Try kaspi_content photos first (enriched, high quality)
        foreach ($patterns as $pattern) {
            $row = DB::table('products as p')
                ->join('source_categories as sc', 'sc.id', '=', 'p.source_category_id')
                ->join('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
                ->where('p.present', 1)
                ->where(function ($q) use ($pattern) {
                    $q->where('sc.name', 'like', $pattern)
                      ->orWhere('sc.path', 'like', $pattern);
                })
                ->whereNotNull('kc.photos_json')
                ->where('kc.photos_json', '!=', '[]')
                ->where('kc.photos_json', '!=', '')
                ->select('kc.photos_json')
                ->first();

            if ($row) {
                $photos = json_decode($row->photos_json, true);
                if (!empty($photos) && isset($photos[0])) {
                    return $photos[0];
                }
            }
        }

        // Fallback: source_photos_json from products table
        foreach ($patterns as $pattern) {
            $row = DB::table('products as p')
                ->join('source_categories as sc', 'sc.id', '=', 'p.source_category_id')
                ->where('p.present', 1)
                ->where(function ($q) use ($pattern) {
                    $q->where('sc.name', 'like', $pattern)
                      ->orWhere('sc.path', 'like', $pattern);
                })
                ->whereNotNull('p.source_photos_json')
                ->where('p.source_photos_json', '!=', '[]')
                ->where('p.source_photos_json', '!=', '')
                ->select('p.source_photos_json')
                ->first();

            if ($row) {
                $photos = json_decode($row->source_photos_json, true);
                if (!empty($photos) && isset($photos[0])) {
                    return $photos[0];
                }
            }
        }

        return null;
    }
}
