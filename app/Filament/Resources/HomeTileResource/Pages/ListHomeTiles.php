<?php

namespace App\Filament\Resources\HomeTileResource\Pages;

use App\Filament\Resources\HomeTileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeTiles extends ListRecords
{
    protected static string $resource = HomeTileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить плитку'),
        ];
    }
}
