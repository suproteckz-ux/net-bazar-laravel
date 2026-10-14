<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CartController;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index()
    {
        $cart     = session('cart', []);
        $products = CartController::loadProducts($cart);

        if (empty($products)) {
            return redirect()->route('cart.index')
                ->with('error', 'Ваша корзина пуста.');
        }

        $subtotal = array_sum(array_column($products, 'line_total'));

        return view('checkout.index', compact('products', 'subtotal'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'phone'         => ['required', 'string', 'max:30'],
            'city'          => ['required', 'string', 'max:120'],
            'comment'       => ['nullable', 'string', 'max:1000'],
        ]);

        $cart     = session('cart', []);
        $products = CartController::loadProducts($cart);

        if (empty($products)) {
            return redirect()->route('cart.index')
                ->with('error', 'Ваша корзина пуста.');
        }

        $subtotal = array_sum(array_column($products, 'line_total'));

        // Server-side re-validation: check every cart item is still orderable.
        // Matches the CartController gate: present=1 AND (available=1 OR preorder).
        $cartSkus       = array_keys($products);
        $availableSkus  = DB::table('products as p')
            ->leftJoin('stock_pp3 as s', 's.sku', '=', 'p.sku')
            ->whereIn('p.sku', $cartSkus)
            ->where('p.present', 1)
            ->where(function ($q) {
                $q->where('p.available', 1)
                  ->orWhere('s.state', 'preorder');
            })
            ->pluck('p.sku')
            ->flip()
            ->all();

        $unavailableNames = [];
        foreach ($products as $sku => $row) {
            if (!isset($availableSkus[$sku])) {
                $unavailableNames[] = $row->name;
            }
        }

        if (!empty($unavailableNames)) {
            return back()->with(
                'error',
                'Следующие товары недоступны для заказа: '
                    . implode(', ', $unavailableNames)
                    . '. Пожалуйста, обновите корзину.'
            );
        }

        $order = DB::transaction(function () use ($validated, $products, $subtotal) {
            $order = Order::create([
                'status'        => 'new',
                'customer_name' => $validated['customer_name'],
                'phone'         => $validated['phone'],
                'city'          => $validated['city'],
                'comment'       => $validated['comment'] ?? null,
                'subtotal_kzt'  => $subtotal,
                'total_kzt'     => $subtotal,
            ]);

            foreach ($products as $row) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'sku'          => $row->sku,
                    'name'         => $row->name,
                    'brand'        => $row->brand,
                    'price_kzt'    => $row->price_kzt,
                    'qty'          => $row->qty,
                    'subtotal_kzt' => $row->line_total,
                ]);
            }

            return $order;
        });

        // Cart cleared only after successful commit
        session()->forget('cart');

        return redirect()->route('checkout.done', ['id' => $order->id]);
    }

    public function done(int $id)
    {
        $order = Order::with('items')->findOrFail($id);
        return view('checkout.done', compact('order'));
    }
}
