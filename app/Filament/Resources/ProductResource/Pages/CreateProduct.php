<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected int $preorderDays = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->preorderDays = (int) ($data['preorder_days'] ?? 0);

        $data['source']                = 'manual';
        $data['exclude_from_feed_sync'] = 1;
        $data['source_offer_id']       = $data['sku'] ?? '';
        $data['source_category_id']    = '__manual__';
        $data['source_category_path']  = '';
        $data['source_description']    = '';
        $data['source_photos_json']    = '[]';
        $data['present']               = $data['present'] ?? true;
        $data['last_imported_at']      = now()->toDateTimeString();

        unset($data['editorial_photos_upload'], $data['preorder_days']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $product = $this->record;

        // Auto-generate url_slug if not set
        if (empty($product->url_slug)) {
            $base = Str::slug($product->name . '-' . $product->sku);
            $slug = $base;
            $i    = 1;
            while (DB::table('products')
                ->where('url_slug', $slug)
                ->where('sku', '!=', $product->sku)
                ->exists()
            ) {
                $slug = $base . '-' . $i++;
            }
            DB::table('products')->where('sku', $product->sku)->update(['url_slug' => $slug]);
            $product->url_slug = $slug;
        }

        // Handle uploaded photos
        $rawState = $this->form->getRawState();
        $uploaded = $rawState['editorial_photos_upload'] ?? [];
        if (!empty($uploaded)) {
            $urls = array_values(array_map(
                fn ($path) => '/storage/' . $path,
                (array) $uploaded
            ));
            DB::table('products')
                ->where('sku', $product->sku)
                ->update(['editorial_photos_json' => json_encode($urls)]);
        }

        // Create stock_pp3 row
        $this->syncStockPp3($product);
    }

    private function syncStockPp3(Product $product): void
    {
        $qty       = (int) $product->quantity;
        $available = (bool) $product->available;

        if ($this->preorderDays > 0 && $available) {
            $state = 'preorder';
        } elseif ($qty > 0 && $available) {
            $state = 'in_stock';
        } else {
            $state = 'out_of_stock';
        }

        DB::table('stock_pp3')->upsert(
            [[
                'sku'              => $product->sku,
                'export_sku'       => $product->sku,
                'match_method'     => 'manual',
                'raw_stock'        => $qty,
                'preorder_days'    => $this->preorderDays,
                'source_available' => $available ? 1 : 0,
                'quantity'         => $state === 'preorder' ? 0 : $qty,
                'state'            => $state,
                'present_export'   => 1,
                'updated_at'       => now()->toDateTimeString(),
            ]],
            ['sku'],
            ['export_sku', 'match_method', 'raw_stock', 'preorder_days',
             'source_available', 'quantity', 'state', 'present_export', 'updated_at']
        );
    }
}
