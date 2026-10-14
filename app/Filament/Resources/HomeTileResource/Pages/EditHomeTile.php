<?php

namespace App\Filament\Resources\HomeTileResource\Pages;

use App\Filament\Resources\HomeTileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHomeTile extends EditRecord
{
    protected static string $resource = HomeTileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
