<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CategoryTreeService;

class CatalogController extends Controller
{
    public function __construct(private CategoryTreeService $tree) {}

    // ─── /catalog ─────────────────────────────────────────────────────────────
    // Supports ?category={kaspi_id} and/or ?q={search}
    // Matches Python catalog() behavior exactly.

    public function index(Request $request)
    {
        $q          = substr(trim($request->query('q', '')), 0, 200);
        $categoryId = $request->query('category', '');

        // Load sidebar tree (same query as Python's public_categories)
        $flatCategories  = $this->tree->getPublicCategories();
        $categoryTree    = $this->tree->buildTree($flatCategories);
        $selectedCategory = null;
        $activeBranchIds = [];

        if ($categoryId !== '') {
            $selectedCategory = $this->tree->findById($categoryId, $flatCategories);
            if ($selectedCategory === null) {
                abort(404, 'Категория не найдена');
            }
            $activeBranchIds = $this->tree->getActiveBranchIds($categoryId, $flatCategories);
        }

        $query = DB::table('products as p')
            ->leftJoin('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
            ->where('p.present', 1)
            ->select(
                'p.sku', 'p.name', 'p.brand', 'p.price_kzt',
                'p.source_photos_json',
                'kc.photos_json as kaspi_photos_json',
                'kc.manual_photos_json'
            )
            ->orderBy('p.name');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('p.sku', $q)
                  ->orWhere('p.name', 'like', '%' . $q . '%')
                  ->orWhere('p.brand', 'like', '%' . $q . '%');
            });
        }

        if ($categoryId !== '' && $selectedCategory !== null) {
            $descendantIds = $this->tree->getDescendantIds($categoryId);
            $query->whereIn('p.sku', function ($sub) use ($descendantIds) {
                $sub->select('a.sku')
                    ->from('product_kaspi_categories as a')
                    ->join('kaspi_content as e', 'e.sku', '=', 'a.sku')
                    ->where('e.status', 'ready')
                    ->whereIn('a.category_id', $descendantIds);
            });
        }

        $products  = $query->paginate(24)->withQueryString();
        $pageTitle = $selectedCategory
            ? $selectedCategory->name
            : ($q !== '' ? 'Поиск: ' . $q : 'Каталог');

        return view('catalog.index', compact(
            'products', 'pageTitle', 'q',
            'categoryTree', 'flatCategories', 'selectedCategory', 'activeBranchIds', 'categoryId'
        ));
    }

    // ─── /catalog/{slug} ──────────────────────────────────────────────────────
    // Marketing shortcut pages (Бытовая техника, Кресла, etc.)
    // Shows the sidebar tree with no active category highlighted.

    public function show(string $slug)
    {
        $categoryName = self::SLUG_NAMES[$slug] ?? null;
        if ($categoryName === null) {
            abort(404);
        }

        $flatCategories  = $this->tree->getPublicCategories();
        $categoryTree    = $this->tree->buildTree($flatCategories);
        $activeBranchIds = [];
        $selectedCategory = null;
        $categoryId       = '';

        $patterns = self::SLUG_PATTERNS[$slug];

        $products = DB::table('products as p')
            ->leftJoin('source_categories as sc', 'sc.id', '=', 'p.source_category_id')
            ->leftJoin('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
            ->where('p.present', 1)
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('sc.name', 'like', $pattern)
                      ->orWhere('sc.path', 'like', $pattern);
                }
            })
            ->select(
                'p.sku', 'p.name', 'p.brand', 'p.price_kzt',
                'p.source_photos_json',
                'kc.photos_json as kaspi_photos_json',
                'kc.manual_photos_json'
            )
            ->orderBy('p.name')
            ->paginate(24);

        return view('catalog.show', compact(
            'products', 'categoryName',
            'categoryTree', 'flatCategories', 'selectedCategory', 'activeBranchIds', 'categoryId'
        ));
    }

    public static function firstPhoto(object $product): ?string
    {
        foreach (['manual_photos_json', 'kaspi_photos_json', 'source_photos_json'] as $field) {
            if (!empty($product->$field) && $product->$field !== '[]') {
                $arr = json_decode($product->$field, true);
                if (!empty($arr[0])) {
                    return $arr[0];
                }
            }
        }
        return null;
    }

    private const SLUG_NAMES = [
        'bytovaya-tehnika' => 'Бытовая техника',
        'kresla'           => 'Кресла',
        'kulery-dlya-vody' => 'Кулеры для воды',
        'himiya'           => 'Химия',
        'avtoaksessuary'   => 'Автоаксессуары',
    ];

    private const SLUG_PATTERNS = [
        'bytovaya-tehnika' => ['%Бытов%', '%техник%', '%Стиральн%', '%Холодильник%', '%Посудомо%'],
        'kresla'           => ['%Кресл%', '%Офисн%'],
        'kulery-dlya-vody' => ['%Кулер%', '%Помп%'],
        'himiya'           => ['%Хими%'],
        'avtoaksessuary'   => ['%Масла и технич%', '%Моторн%масл%', '%Автотовар%'],
    ];
}
