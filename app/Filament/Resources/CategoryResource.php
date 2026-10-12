<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\KaspiCategory;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model         = KaspiCategory::class;
    protected static ?string $label         = 'Категория';
    protected static ?string $pluralLabel   = 'Категории';
    protected static ?int    $navigationSort = 2;

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-folder';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Каталог';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название (Kaspi)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('override.display_name')
                    ->label('Отображаемое название')
                    ->placeholder('—')
                    ->limit(50),
                Tables\Columns\TextColumn::make('override.display_order')
                    ->label('Порядок')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->label('Путь')
                    ->limit(60),
                Tables\Columns\IconColumn::make('override.seo_title')
                    ->label('SEO')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => (bool) optional($record->override)->seo_title),
            ])
            ->defaultSort('name')
            ->filters([])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Базовая категория (из Kaspi, только для чтения)')
                ->schema([
                    TextInput::make('id')
                        ->label('ID категории')
                        ->disabled(),
                    TextInput::make('name')
                        ->label('Название из Kaspi')
                        ->disabled()
                        ->helperText('Перезаписывается при каждом импорте из фида — не редактировать'),
                    TextInput::make('path')
                        ->label('Путь в дереве категорий')
                        ->disabled(),
                ])
                ->columns(1),

            Section::make('Отображение в каталоге')
                ->description('Сохраняются между импортами. Не перезаписываются Kaspi-синком.')
                ->schema([
                    TextInput::make('display_name')
                        ->label('Название для отображения')
                        ->helperText('Если заполнено — показывается вместо названия из Kaspi на сайте')
                        ->maxLength(255),
                    TextInput::make('display_order')
                        ->label('Порядок сортировки')
                        ->numeric()
                        ->helperText('Меньшее число — выше в списке. Оставьте пустым — сортировка по алфавиту'),
                ])
                ->columns(2),

            Section::make('SEO')
                ->description('Не перезаписываются Kaspi-синком.')
                ->schema([
                    TextInput::make('seo_title')
                        ->label('Title страницы')
                        ->maxLength(255)
                        ->placeholder('Если пусто — генерируется автоматически'),
                    TextInput::make('meta_description')
                        ->label('Meta description')
                        ->maxLength(500),
                    TextInput::make('h1')
                        ->label('H1 заголовок')
                        ->maxLength(255)
                        ->placeholder('Если пусто — используется название категории'),
                    Textarea::make('seo_text')
                        ->label('SEO-текст (отображается под товарами)')
                        ->rows(6)
                        ->maxLength(10000),
                ])
                ->columns(1),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'edit'  => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
