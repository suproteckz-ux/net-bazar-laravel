<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManualCategoryResource\Pages;
use App\Models\ManualCategory;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualCategoryResource extends Resource
{
    protected static ?string $model         = ManualCategory::class;
    protected static ?string $label         = 'Ручная категория';
    protected static ?string $pluralLabel   = 'Ручные категории';
    protected static ?int    $navigationSort = 3;

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-folder-plus';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Каталог';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Основное')
                ->schema([
                    TextInput::make('name')
                        ->label('Название категории')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set, $get) {
                            if (empty($get('slug'))) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Slug (URL)')
                        ->required()
                        ->maxLength(255)
                        ->unique(table: 'manual_categories', column: 'slug', ignoreRecord: true)
                        ->rules(['regex:/^[a-z0-9-]+$/'])
                        ->helperText('Только строчные буквы, цифры и дефисы. URL страницы: /category/{slug}'),

                    Toggle::make('is_active')
                        ->label('Активна (показывается на сайте)')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->label('Порядок сортировки')
                        ->numeric()
                        ->default(0)
                        ->helperText('Меньшее число — выше в списке'),
                ])
                ->columns(2),

            Section::make('Расположение в каталоге')
                ->description('Выберите не более одного родителя — Kaspi-категория ИЛИ ручная. Взаимно исключают друг друга.')
                ->schema([
                    Select::make('kaspi_parent_id')
                        ->label('Родительская Kaspi-категория')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => DB::table('kaspi_categories')
                            ->where('path', 'like', '%' . $search . '%')
                            ->orderBy('path')
                            ->limit(50)
                            ->pluck('path', 'id')
                            ->toArray()
                        )
                        ->getOptionLabelUsing(fn ($value): ?string => DB::table('kaspi_categories')
                            ->where('id', $value)
                            ->value('path')
                        )
                        ->nullable()
                        ->placeholder('— нет —')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $state ? $set('parent_id', null) : null)
                        ->rules([
                            fn (Get $get) => function (string $attr, $value, \Closure $fail) use ($get) {
                                if ($value && $get('parent_id')) {
                                    $fail('Нельзя одновременно задать Kaspi-родителя и ручного родителя.');
                                }
                            },
                        ]),

                    Select::make('parent_id')
                        ->label('Родительская ручная категория')
                        ->options(fn () => ManualCategory::where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                        )
                        ->searchable()
                        ->nullable()
                        ->placeholder('— нет —')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $state ? $set('kaspi_parent_id', null) : null)
                        ->rules([
                            fn (Get $get) => function (string $attr, $value, \Closure $fail) use ($get) {
                                if ($value && $get('kaspi_parent_id')) {
                                    $fail('Нельзя одновременно задать ручного родителя и Kaspi-родителя.');
                                }
                            },
                        ]),
                ])
                ->columns(2),

            Section::make('SEO и контент')
                ->schema([
                    TextInput::make('h1')
                        ->label('H1 заголовок')
                        ->maxLength(255)
                        ->placeholder('Если пусто — используется название'),

                    TextInput::make('seo_title')
                        ->label('Title страницы')
                        ->maxLength(255)
                        ->placeholder('Если пусто — "{name} — NetBazar"'),

                    TextInput::make('seo_description')
                        ->label('Meta description')
                        ->maxLength(500),

                    Textarea::make('description')
                        ->label('Описание (SEO-текст под товарами)')
                        ->rows(5)
                        ->maxLength(10000),
                ])
                ->columns(1),

            Section::make('Изображение')
                ->schema([
                    FileUpload::make('image_path')
                        ->label('Изображение категории')
                        ->image()
                        ->directory('manual-categories')
                        ->disk('public')
                        ->visibility('public')
                        ->imagePreviewHeight('150')
                        ->nullable(),
                ])
                ->columns(1),

            Section::make('Товары в категории')
                ->description('Поиск по названию или артикулу. Изменения сохраняются вместе с формой.')
                ->schema([
                    Select::make('products')
                        ->label('Назначенные товары')
                        ->relationship(
                            name: 'products',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->where('present', 1)->orderBy('name'),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload(false)
                        ->optionsLimit(50)
                        ->getOptionLabelFromRecordUsing(
                            fn (Product $record) => "[{$record->sku}] {$record->name}"
                        ),

                    Textarea::make('bulk_skus')
                        ->label('Массовое добавление товаров (по одному артикулу на строку)')
                        ->helperText('Вставьте артикулы (SKU), каждый на новой строке. Будут добавлены к уже назначенным. Недействительные артикулы игнорируются.')
                        ->rows(5)
                        ->dehydrated(false)
                        ->nullable(),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->formatStateUsing(fn ($state) => '/category/' . $state),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Товаров')
                    ->getStateUsing(fn (ManualCategory $record) => $record->products()->count())
                    ->sortable(false),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Изменена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Статус')
                    ->trueLabel('Активные')
                    ->falseLabel('Неактивные'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('view_storefront')
                    ->label('Открыть на сайте')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (ManualCategory $record) => url('/category/' . $record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (ManualCategory $record) => $record->is_active),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListManualCategories::route('/'),
            'create' => Pages\CreateManualCategory::route('/create'),
            'edit'   => Pages\EditManualCategory::route('/{record}/edit'),
        ];
    }
}
