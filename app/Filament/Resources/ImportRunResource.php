<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportRunResource\Pages;
use App\Models\ImportRun;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ImportRunResource extends Resource
{
    protected static ?string $model       = ImportRun::class;
    protected static ?string $label       = 'Импорт';
    protected static ?string $pluralLabel = 'История импорта';
    protected static ?int    $navigationSort = 1;

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-arrow-path';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Интеграции';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('time')
                    ->label('Дата (UTC)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('offer_count')
                    ->label('Товаров в фиде')
                    ->numeric(thousandsSeparator: ' ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('result')
                    ->label('Результат')
                    ->badge()
                    ->getStateUsing(fn ($record) => json_decode($record->report_json, true)['result'] ?? '—')
                    ->color(fn ($state) => match($state) {
                        'success'  => 'success',
                        'rejected' => 'warning',
                        'error'    => 'danger',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sha256')
                    ->label('SHA256')
                    ->limit(12),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportRuns::route('/'),
        ];
    }
}
