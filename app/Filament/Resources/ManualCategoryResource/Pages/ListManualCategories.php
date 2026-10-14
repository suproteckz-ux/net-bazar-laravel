<?php

namespace App\Filament\Resources\ManualCategoryResource\Pages;

use App\Filament\Resources\ManualCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListManualCategories extends ListRecords
{
    protected static string $resource = ManualCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
