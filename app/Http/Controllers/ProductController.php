<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function show(string $sku)
    {
        // 1. Check alias table — redirect old SKUs to new canonical SKU
        $alias = DB::table('catalog_sku_aliases')
            ->where('old_sku', $sku)
            ->first();

        if ($alias) {
            return redirect()->route('product.show', ['sku' => $alias->new_sku], 301);
        }

        // 2. Load product with enrichment and stock in one query
        $product = DB::table('products as p')
            ->leftJoin('kaspi_content as kc', 'kc.sku', '=', 'p.sku')
            ->leftJoin('stock_pp3 as s', 's.sku', '=', 'p.sku')
            ->leftJoin('source_categories as sc', 'sc.id', '=', 'p.source_category_id')
            ->where('p.sku', $sku)
            ->select(
                'p.sku', 'p.name', 'p.brand', 'p.price_kzt', 'p.present',
                'p.source_photos_json', 'p.source_category_id',
                'sc.name as category_name', 'sc.path as category_path',
                'kc.photos_json as kaspi_photos_json',
                'kc.manual_photos_json',
                'kc.description as kaspi_description',
                'kc.attributes_json',
                'kc.status as kaspi_status',
                'kc.url as kaspi_url',
                's.state as stock_state',
                's.quantity as stock_quantity',
                's.preorder_days'
            )
            ->first();

        if (!$product) {
            abort(404, "Товар с артикулом «{$sku}» не найден.");
        }

        $photos     = $this->resolvePhotos($product);
        $attributes = $this->parseAttributes($product->attributes_json ?? null);
        $stockLabel = $this->stockLabel($product);

        return view('product.show', compact('product', 'photos', 'attributes', 'stockLabel'));
    }

    private function resolvePhotos(object $product): array
    {
        foreach (['manual_photos_json', 'kaspi_photos_json', 'source_photos_json'] as $field) {
            if (!empty($product->$field) && $product->$field !== '[]') {
                $arr = json_decode($product->$field, true);
                if (!empty($arr)) {
                    return array_values($arr);
                }
            }
        }
        return [];
    }

    private function parseAttributes(?string $json): array
    {
        if (empty($json) || $json === '[]' || $json === '{}') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        // Handle both [{name:..., value:...}] and {key: value} formats
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['name'])) {
                $result[] = ['name' => $value['name'], 'value' => $value['value'] ?? ''];
            } elseif (is_string($value)) {
                $result[] = ['name' => $key, 'value' => $value];
            }
        }
        return $result;
    }

    private function stockLabel(object $product): string
    {
        $state = $product->stock_state ?? null;
        $days  = (int)($product->preorder_days ?? 0);

        if ($state === 'in_stock') {
            return 'В наличии';
        }
        if ($state === 'preorder') {
            return $days > 0 ? "Под заказ {$days} дн." : 'Под заказ';
        }
        if ($state === 'out_of_stock') {
            return 'Нет в наличии';
        }
        return '';
    }
}
