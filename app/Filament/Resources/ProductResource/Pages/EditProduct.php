<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected int $preorderDays = 0;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn () => $this->record?->source === 'manual'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->preorderDays = (int) ($data['preorder_days'] ?? 0);
        unset($data['editorial_photos_upload'], $data['preorder_days']);
        return $data;
    }

    protected function afterSave(): void
    {
        $product = $this->record;

        // Handle uploaded photos for manual products
        $rawState = $this->form->getRawState();
        $uploaded = $rawState['editorial_photos_upload'] ?? [];
        if (!empty($uploaded) && $product->source === 'manual') {
            $urls = array_values(array_map(
                fn ($path) => '/storage/' . $path,
                (array) $uploaded
            ));
            DB::table('products')
                ->where('sku', $product->sku)
                ->update(['editorial_photos_json' => json_encode($urls)]);
        }

        // Sync stock_pp3 for manual products
        if ($product->source === 'manual') {
            $this->syncStockPp3($product);
        }
    }

    private function syncStockPp3(Product $product): void
    {
        // Re-fetch to get latest saved values
        $row = DB::table('products')->where('sku', $product->sku)
            ->select('quantity', 'available')->first();

        $qty       = (int) ($row?->quantity ?? 0);
        $available = (bool) ($row?->available ?? false);

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
