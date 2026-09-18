<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model       = Product::class;
    protected static ?string $label       = 'Товар';
    protected static ?string $pluralLabel = 'Товары';
    protected static ?int    $navigationSort = 1;

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Каталог';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('Артикул')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Наименование')
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Бренд')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_kzt')
                    ->label('Цена, ₸')
                    ->numeric(thousandsSeparator: ' ')
                    ->sortable(),
                Tables\Columns\IconColumn::make('present')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('present')
                    ->label('Статус')
                    ->options([
                        '1' => 'Активен',
                        '0' => 'Скрыт',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Идентификатор')
                    ->schema([
                        TextInput::make('sku')
                            ->label('Артикул (SKU)')
                            ->disabled()
                            ->helperText('Артикул является первичным ключом и не редактируется'),
                    ])
                    ->columns(1),

                Section::make('Видимость')
                    ->schema([
                        Toggle::make('present')
                            ->label('Товар активен (показывается в каталоге)')
                            ->helperText('При следующем импорте из фида значение будет установлено в "Активен" автоматически'),
                    ])
                    ->columns(1),

                Section::make('Данные из фида (перезаписываются при каждом импорте)')
                    ->schema([
                        TextInput::make('name')
                            ->label('Наименование')
                            ->maxLength(500)
                            ->helperText('⚠ Перезаписывается при следующем импорте из MarketRadar'),
                        TextInput::make('brand')
                            ->label('Бренд')
                            ->maxLength(200)
                            ->helperText('⚠ Перезаписывается при следующем импорте'),
                        TextInput::make('price_kzt')
                            ->label('Цена, ₸')
                            ->numeric()
                            ->helperText('⚠ Перезаписывается при следующем импорте'),
                        TextInput::make('site_category_override')
                            ->label('Переопределение категории (site_category_override)')
                            ->maxLength(255)
                            ->placeholder('Оставьте пустым для автоматической категории'),
                    ])
                    ->columns(2),

                Section::make('Редакционный контент (сохраняется между импортами)')
                    ->schema([
                        Textarea::make('editorial_description')
                            ->label('Редакционное описание')
                            ->helperText('Если заполнено — отображается вместо описания из Kaspi. Не перезаписывается при импорте.')
                            ->rows(6)
                            ->maxLength(10000),
                        Textarea::make('editorial_photos_json')
                            ->label('Редакционные фото (JSON-массив URL)')
                            ->helperText('Пример: ["https://cdn.example.com/photo1.jpg"]. Если заполнено — используется вместо фото из Kaspi. Не перезаписывается при импорте.')
                            ->rows(4)
                            ->maxLength(10000),
                    ])
                    ->columns(1),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextEntry::make('sku')->label('Артикул'),
                        TextEntry::make('name')->label('Наименование'),
                        TextEntry::make('brand')->label('Бренд')->placeholder('—'),
                        TextEntry::make('price_kzt')->label('Цена, ₸')->numeric(thousandsSeparator: ' '),
                        TextEntry::make('quantity')->label('Количество'),
                        TextEntry::make('present')->label('Активен')->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Да' : 'Нет')
                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                    ])
                    ->columns(2),

                Section::make('Kaspi')
                    ->schema([
                        TextEntry::make('kaspi_confirmed_url')
                            ->label('Kaspi URL (подтверждённый)')
                            ->placeholder('Не найден')
                            ->getStateUsing(function ($record) {
                                $row = \Illuminate\Support\Facades\DB::table('kaspi_lookup')
                                    ->where('sku', $record->sku)
                                    ->where('status', 'resolved')
                                    ->value('url');
                                return $row
                                    ? $row . '?m=' . config('netbazar.kaspi_partner_id')
                                    : null;
                            })
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(1),

                Section::make('Редакционный контент')
                    ->schema([
                        TextEntry::make('editorial_description')
                            ->label('Редакционное описание')
                            ->placeholder('—'),
                        TextEntry::make('editorial_photos_json')
                            ->label('Редакционные фото (JSON)')
                            ->placeholder('—'),
                        TextEntry::make('site_category_override')
                            ->label('Переопределение категории')
                            ->placeholder('—'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'view'  => Pages\ViewProduct::route('/{record}'),
            'edit'  => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
