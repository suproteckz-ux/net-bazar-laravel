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
    // When a category has url_slug → 301 redirect to /catalog/{slug}

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
            // Direct DB lookup for slug redirect — independent of the public tree.
            // This fires even when the category isn't in getPublicCategories() (e.g. no ready products yet).
            $rawCat = DB::table('kaspi_categories')
                ->where('id', $categoryId)
                ->select(['id', 'url_slug'])
                ->first();

            if ($rawCat === null) {
                abort(404, 'Категория не найдена');
            }

            if ($rawCat->url_slug !== null) {
                $dest = route('catalog.show', ['slug' => $rawCat->url_slug]);
                if ($q !== '') {
                    $dest .= '?q=' . rawurlencode($q);
                }
                return redirect($dest, 301);
            }

            // No slug yet — fall through to tree-based display
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

        $query->orderByRaw(self::sortSql())->orderBy('p.name')->orderBy('p.sku');
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
    // Step 1: Check kaspi_categories for a matching url_slug (canonical SEO page)
    // Step 2: Fall through to the 5 hardcoded marketing shortcut pages

    public function show(string $slug)
    {
        // ── Step 1: Kaspi category SEO page ──────────────────────────────────
        $kaspiCat = DB::table('kaspi_categories as kc')
            ->leftJoin('kaspi_category_overrides as o', 'o.category_id', '=', 'kc.id')
            ->where('kc.url_slug', $slug)
            ->select(
                'kc.id', 'kc.parent_id', 'kc.name', 'kc.path', 'kc.url_slug',
                DB::raw('COALESCE(o.display_name, kc.name) AS display_name'),
                'o.seo_title', 'o.meta_description', 'o.h1', 'o.seo_text'
            )
            ->first();

        if ($kaspiCat !== null) {
            return $this->showKaspiCategory($kaspiCat);
        }

        // ── Step 2: Marketing shortcut pages ─────────────────────────────────
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
                'p.sku', 'p.url_slug', 'p.name', 'p.brand', 'p.price_kzt',
                'p.source_photos_json',
                'kc.photos_json as kaspi_photos_json',
                'kc.manual_photos_json'
            )
            ->orderByRaw(self::sortSql())
            ->orderBy('p.name')
            ->orderBy('p.sku')
            ->paginate(24);

        return view('catalog.show', compact(
            'products', 'categoryName',
            'categoryTree', 'flatCategories', 'selectedCategory', 'activeBranchIds', 'categoryId'
        ));
    }

    // ─── Kaspi category page (shared by show() step 1) ───────────────────────

    private function showKaspiCategory(object $kaspiCat): \Illuminate\View\View
    {
        $q = substr(trim(request()->query('q', '')), 0, 200);

        $flatCategories  = $this->tree->getPublicCategories();
        $categoryTree    = $this->tree->buildTree($flatCategories);
        $categoryId      = $kaspiCat->id;
        $selectedCategory = $this->tree->findById($categoryId, $flatCategories);

        // If the category is not in the public tree yet (no products, edge case),
        // use the raw DB object so the page still renders correctly.
        if ($selectedCategory === null) {
            $selectedCategory = (object) [
                'id'       => $kaspiCat->id,
                'parent_id'=> $kaspiCat->parent_id,
                'name'     => $kaspiCat->display_name,
                'path'     => $kaspiCat->path,
                'url_slug' => $kaspiCat->url_slug,
            ];
        }

        $activeBranchIds = $this->tree->getActiveBranchIds($categoryId, $flatCategories);

        // Ancestor chain for breadcrumbs — loaded directly from kaspi_categories so
        // it works even when the category is absent from the public tree (no ready products).
        $ancestors = $this->loadCategoryAncestors($categoryId);

        $query = DB::table('products as p')
            ->leftJoin('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
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

        $descendantIds = $this->tree->getDescendantIds($categoryId);
        $query->whereIn('p.sku', function ($sub) use ($descendantIds) {
            $sub->select('a.sku')
                ->from('product_kaspi_categories as a')
                ->join('kaspi_content as e', 'e.sku', '=', 'a.sku')
                ->where('e.status', 'ready')
                ->whereIn('a.category_id', $descendantIds);
        });

        $query->orderByRaw(self::sortSql())->orderBy('p.name')->orderBy('p.sku');
        $products = $query->paginate(24)->withQueryString();

        $displayName = $kaspiCat->display_name;
        $pageTitle   = $kaspiCat->h1 ?: $displayName;
        $seoTitle    = $kaspiCat->seo_title ?: ($displayName . ' — NetBazar');
        $metaDescription = $kaspiCat->meta_description ?: ('Купить ' . mb_strtolower($displayName) . ' в Алматы и с доставкой по Казахстану. Широкий выбор, гарантия качества — NetBazar.');
        $seoText     = $kaspiCat->seo_text;

        return view('catalog.category', compact(
            'products', 'pageTitle', 'seoTitle', 'metaDescription', 'seoText', 'q',
            'kaspiCat', 'ancestors', 'categoryTree', 'flatCategories', 'selectedCategory',
            'activeBranchIds', 'categoryId'
        ));
    }

    /**
     * Load the ancestor chain for a kaspi_category, from root down to (and including) the category itself.
     * Queries kaspi_categories directly — works even if the category is absent from the public tree.
     */
    private function loadCategoryAncestors(string $categoryId): array
    {
        // Walk up via parent_id; SQLite recursive CTEs work the same as MySQL here.
        $byId = DB::table('kaspi_categories')
            ->select(['id', 'parent_id', 'name', 'url_slug'])
            ->get()
            ->keyBy('id')
            ->all();

        $chain = [];
        $current = $byId[$categoryId] ?? null;
        while ($current !== null) {
            array_unshift($chain, $current);
            $current = $byId[$current->parent_id ?? null] ?? null;
        }
        return $chain;
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

    // ─── Sort: in-stock+photo first, then in-stock, then photo-only, then no stock/photo ─
    public static function sortSql(): string
    {
        $hasPhoto = "(
            (kc.manual_photos_json IS NOT NULL AND kc.manual_photos_json NOT IN ('', '[]', 'null'))
            OR (kc.photos_json IS NOT NULL AND kc.photos_json NOT IN ('', '[]', 'null'))
            OR (p.source_photos_json IS NOT NULL AND p.source_photos_json NOT IN ('', '[]', 'null'))
        )";
        return "CASE
            WHEN p.quantity > 0 AND {$hasPhoto} THEN 1
            WHEN p.quantity > 0                  THEN 2
            WHEN {$hasPhoto}                      THEN 3
            ELSE 4
        END";
    }

    /**
     * Slugs of existing marketing shortcut pages (/catalog/{slug}).
     * These must never be assigned to kaspi categories during slug generation
     * to avoid silently replacing the marketing page with a different category.
     * GenerateCategorySlugs reads this list to pre-populate its reserved pool.
     */
    public static function marketingPageSlugs(): array
    {
        return array_keys(self::SLUG_NAMES);
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
