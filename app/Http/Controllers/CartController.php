<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index()
    {
        $cart     = session('cart', []);
        $products = $this->loadProducts($cart);

        return view('cart.index', compact('cart', 'products'));
    }

    public function store(Request $request)
    {
        $sku = trim($request->input('sku', ''));
        if (empty($sku)) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Товар не найден.'], 422);
            }
            return back()->with('error', 'Товар не найден.');
        }

        // Require present=1 AND (available=1 OR explicit preorder from stock_pp3).
        // products.available is kept in sync by CatalogImport: 1 = in_stock, 0 = absent/zeroed.
        // Products without a stock_pp3 row (manually added) pass if available=1.
        $orderable = DB::table('products as p')
            ->leftJoin('stock_pp3 as s', 's.sku', '=', 'p.sku')
            ->where('p.sku', $sku)
            ->where('p.present', 1)
            ->where(function ($q) {
                $q->where('p.available', 1)
                  ->orWhere('s.state', 'preorder');
            })
            ->exists();

        if (!$orderable) {
            if ($request->wantsJson()) {
                $productName = DB::table('products')->where('sku', $sku)->value('name') ?? $sku;
                return response()->json([
                    'ok'           => false,
                    'type'         => 'out_of_stock',
                    'message'      => 'Товар недоступен для заказа.',
                    'product_name' => $productName,
                    'sku'          => $sku,
                ], 409);
            }
            return back()->with('error', 'Товар недоступен для заказа.');
        }

        $cart = session('cart', []);
        $qty  = max(1, (int) $request->input('qty', 1));

        if (isset($cart[$sku])) {
            $cart[$sku]['qty'] += $qty;
        } else {
            $cart[$sku] = ['sku' => $sku, 'qty' => $qty];
        }

        session(['cart' => $cart]);

        if ($request->wantsJson()) {
            $cartCount = array_sum(array_column($cart, 'qty'));
            $p = DB::table('products')->where('sku', $sku)->select('name', 'brand', 'price_kzt')->first();
            $ga4Item = $p ? [
                'item_id'    => $sku,
                'item_name'  => $p->name,
                'item_brand' => $p->brand,
                'price'      => (float) $p->price_kzt,
                'quantity'   => $qty,
                'currency'   => 'KZT',
            ] : ['item_id' => $sku, 'quantity' => $qty];
            return response()->json(['ok' => true, 'cart_count' => $cartCount, 'ga4_item' => $ga4Item]);
        }
        return back()->with('success', 'Товар добавлен в корзину.');
    }

    public function update(Request $request, string $sku)
    {
        $qty  = max(1, (int) $request->input('qty', 1));
        $cart = session('cart', []);

        if (isset($cart[$sku])) {
            $cart[$sku]['qty'] = $qty;
            session(['cart' => $cart]);
        }

        return back();
    }

    public function destroy(string $sku)
    {
        $cart = session('cart', []);
        unset($cart[$sku]);
        session(['cart' => $cart]);

        return back();
    }

    public function clear()
    {
        session()->forget('cart');
        return redirect()->route('cart.index');
    }

    // Reload live product data for a cart (sku => qty map)
    public static function loadProducts(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }

        $rows = DB::table('products as p')
            ->leftJoin('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
            ->whereIn('p.sku', array_keys($cart))
            ->select('p.sku', 'p.url_slug', 'p.name', 'p.brand', 'p.price_kzt',
                     'p.source_photos_json',
                     'kc.photos_json as kaspi_photos_json',
                     'kc.manual_photos_json')
            ->get()
            ->keyBy('sku');

        $result = [];
        foreach ($cart as $sku => $item) {
            if (!isset($rows[$sku])) {
                continue; // product removed from catalog — skip silently
            }
            $row            = $rows[$sku];
            $row->qty       = $item['qty'];
            $row->line_total = (float) $row->price_kzt * $item['qty'];
            $row->photo     = self::firstPhoto($row);
            $result[$sku]   = $row;
        }

        return $result;
    }

    private static function firstPhoto(object $product): ?string
    {
        foreach (['manual_photos_json', 'kaspi_photos_json', 'source_photos_json'] as $field) {
            $val = $product->$field ?? null;
            if (!empty($val) && $val !== '[]') {
                $arr = json_decode($val, true);
                if (!empty($arr)) {
                    return array_values($arr)[0];
                }
            }
        }
        return null;
    }
}
