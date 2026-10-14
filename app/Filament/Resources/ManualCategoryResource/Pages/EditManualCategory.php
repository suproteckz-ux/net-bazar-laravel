<?php

namespace App\Filament\Resources\ManualCategoryResource\Pages;

use App\Filament\Resources\ManualCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditManualCategory extends EditRecord
{
    protected static string $resource = ManualCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->processBulkSkus($this->record->id);
    }

    private function processBulkSkus(int $categoryId): void
    {
        $raw = $this->form->getRawState()['bulk_skus'] ?? '';
        if (empty(trim($raw))) {
            return;
        }

        $skus = collect(explode("\n", $raw))
            ->map(fn ($s) => trim($s))
            ->filter(fn ($s) => $s !== '')
            ->unique()
            ->values();

        $existingSkus = DB::table('products')
            ->whereIn('sku', $skus)
            ->pluck('sku');

        foreach ($existingSkus as $sku) {
            DB::table('product_manual_categories')->insertOrIgnore([
                'sku'                => $sku,
                'manual_category_id' => $categoryId,
                'sort_order'         => 0,
            ]);
        }
    }
}
