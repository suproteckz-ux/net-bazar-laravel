<?php

namespace App\Http\Controllers;

use App\Models\ManualCategory;
use App\Services\CategoryTreeService;
use Illuminate\Support\Facades\DB;

class ManualCategoryController extends Controller
{
    public function __construct(private CategoryTreeService $tree) {}

    public function show(string $slug)
    {
        $category = ManualCategory::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            abort(404);
        }

        $q = substr(trim(request()->query('q', '')), 0, 200);

        $flatCategories  = $this->tree->getPublicCategories();
        $categoryTree    = $this->tree->buildTree($flatCategories);
        $activeBranchIds = [];
        $selectedCategory = null;
        $categoryId = '';

        $query = DB::table('products as p')
            ->join('product_manual_categories as pmc', 'pmc.sku', '=', 'p.sku')
            ->leftJoin('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
            ->where('pmc.manual_category_id', $category->id)
            ->where('p.present', 1)
            ->select(
                'p.sku', 'p.url_slug', 'p.name', 'p.brand', 'p.price_kzt',
                'p.source_photos_json',
                'kc.photos_json as kaspi_photos_json',
                'kc.manual_photos_json'
            );

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('p.sku', $q)
                  ->orWhere('p.name', 'like', '%' . $q . '%')
                  ->orWhere('p.brand', 'like', '%' . $q . '%');
            });
        }

        $query->orderByRaw(CatalogController::sortSql())
              ->orderBy('pmc.sort_order')
              ->orderBy('p.name')
              ->orderBy('p.sku');

        $products = $query->paginate(24)->withQueryString();

        $pageTitle       = $category->h1 ?: $category->name;
        $seoTitle        = $category->seo_title ?: ($category->name . ' — NetBazar');
        $metaDescription = $category->seo_description
            ?: ('Купить ' . mb_strtolower($category->name) . ' в Алматы и с доставкой по Казахстану. Широкий выбор, гарантия качества — NetBazar.');

        return view('manual-category.show', compact(
            'category', 'products', 'pageTitle', 'seoTitle', 'metaDescription', 'q',
            'categoryTree', 'flatCategories', 'selectedCategory', 'activeBranchIds', 'categoryId'
        ));
    }
}
