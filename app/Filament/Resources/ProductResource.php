<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Tables\Columns\BadgeColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn ($state) => $state === 'manual' ? 'Ручной' : 'Фид')
                    ->color(fn ($state) => $state === 'manual' ? 'info' : 'gray'),
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
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options([
                        'feed'   => 'Из фида',
                        'manual' => 'Ручные',
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
                // ── Идентификатор ────────────────────────────────────────────────────
                Section::make('Идентификатор')
                    ->schema([
                        TextInput::make('sku')
                            ->label('Артикул (SKU)')
                            ->required()
                            ->maxLength(100)
                            ->unique('products', 'sku', ignoreRecord: true)
                            ->disabled(fn (?Product $record) => $record !== null)
                            ->dehydrated(fn (?Product $record) => $record === null)
                            ->helperText(fn (?Product $record) => $record !== null
                                ? 'Артикул является первичным ключом и не редактируется'
                                : 'Уникальный артикул товара. Не может быть изменён после создания.'),

                        TextInput::make('url_slug')
                            ->label('URL Slug (SEO)')
                            ->maxLength(255)
                            ->unique('products', 'url_slug', ignoreRecord: true)
                            ->nullable()
                            ->helperText('Если оставить пустым при создании — генерируется автоматически из названия и артикула'),
                    ])
                    ->columns(2),

                // ── Источник (только при редактировании) ─────────────────────────────
                Section::make('Источник и защита от импорта')
                    ->schema([
                        Placeholder::make('source_display')
                            ->label('Источник')
                            ->content(fn (?Product $record) => match($record?->source) {
                                'manual' => 'Ручной товар (создан вручную в CMS)',
                                'feed'   => 'MarketRadar XML (синхронизируется автоматически)',
                                default  => '—',
                            }),

                        Toggle::make('exclude_from_feed_sync')
                            ->label('Защита от перезаписи XML-фидом')
                            ->helperText('Если включено — следующий импорт не затронет цену, остаток и название этого товара'),
                    ])
                    ->columns(2)
                    ->visible(fn (?Product $record) => $record !== null),

                // ── Видимость ────────────────────────────────────────────────────────
                Section::make('Видимость')
                    ->schema([
                        Toggle::make('present')
                            ->label('Товар активен (показывается в каталоге)')
                            ->default(true),
                    ])
                    ->columns(1),

                // ── Коммерческие данные ──────────────────────────────────────────────
                Section::make('Данные товара')
                    ->schema([
                        TextInput::make('name')
                            ->label('Наименование')
                            ->required()
                            ->maxLength(500)
                            ->helperText(fn (?Product $record) =>
                                ($record?->source === 'feed' && !$record?->exclude_from_feed_sync)
                                    ? '⚠ Перезаписывается при следующем импорте из MarketRadar'
                                    : null),

                        TextInput::make('brand')
                            ->label('Бренд')
                            ->maxLength(200)
                            ->helperText(fn (?Product $record) =>
                                ($record?->source === 'feed' && !$record?->exclude_from_feed_sync)
                                    ? '⚠ Перезаписывается при следующем импорте'
                                    : null),

                        TextInput::make('price_kzt')
                            ->label('Цена, ₸')
                            ->numeric()
                            ->required()
                            ->helperText(fn (?Product $record) =>
                                ($record?->source === 'feed' && !$record?->exclude_from_feed_sync)
                                    ? '⚠ Перезаписывается при следующем импорте'
                                    : null),

                        TextInput::make('site_category_override')
                            ->label('Переопределение категории Kaspi')
                            ->maxLength(255)
                            ->placeholder('Оставьте пустым для автоматической категории'),
                    ])
                    ->columns(2),

                // ── Остатки (только для ручных товаров и создания) ───────────────────
                Section::make('Остатки')
                    ->description('Управляются вручную. При сохранении обновляется stock_pp3.')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Количество на складе')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('available')
                            ->label('Доступен для заказа')
                            ->default(true),

                        TextInput::make('preorder_days')
                            ->label('Срок под заказ (дней)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('0 = не под заказ; больше 0 = под заказ N дней')
                            ->dehydrated(false),
                    ])
                    ->columns(3)
                    ->visible(fn (?Product $record) => $record === null || $record?->source === 'manual'),

                // ── Редакционный контент ─────────────────────────────────────────────
                Section::make('Редакционный контент')
                    ->schema([
                        Textarea::make('editorial_description')
                            ->label('Описание товара')
                            ->helperText('Если заполнено — отображается вместо описания из Kaspi. Не перезаписывается при импорте.')
                            ->rows(6)
                            ->maxLength(10000),

                        // FileUpload for manual products: photos stored in public storage
                        FileUpload::make('editorial_photos_upload')
                            ->label('Загрузить фото товара')
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->directory('product-photos')
                            ->reorderable()
                            ->maxFiles(10)
                            ->dehydrated(false)
                            ->helperText('Фото сохраняются как редакционные (приоритет над Kaspi). Не перезаписываются при импорте.')
                            ->visible(fn (?Product $record) => $record === null || $record?->source === 'manual'),

                        // JSON textarea for feed products (backward-compatible manual URL editing)
                        Textarea::make('editorial_photos_json')
                            ->label('Редакционные фото (JSON-массив URL)')
                            ->helperText('Пример: ["https://cdn.example.com/photo1.jpg"]. Не перезаписывается при импорте.')
                            ->rows(3)
                            ->maxLength(10000)
                            ->visible(fn (?Product $record) => $record?->source === 'feed'),
                    ])
                    ->columns(1),

                // ── Ручные категории ─────────────────────────────────────────────────
                Section::make('Ручные категории')
                    ->description('Не перезаписываются при импорте из MarketRadar.')
                    ->schema([
                        Select::make('manualCategories')
                            ->label('Категории')
                            ->relationship(
                                name: 'manualCategories',
                                titleAttribute: 'name',
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Товар может быть в нескольких ручных категориях одновременно. Kaspi-категория не затрагивается.'),
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
                        TextEntry::make('source')->label('Источник')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state === 'manual' ? 'Ручной' : 'Фид')
                            ->color(fn ($state) => $state === 'manual' ? 'info' : 'gray'),
                        TextEntry::make('present')->label('Активен')->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Да' : 'Нет')
                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                        TextEntry::make('exclude_from_feed_sync')->label('Защита от импорта')->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Включена' : 'Выключена')
                            ->color(fn ($state) => $state ? 'warning' : 'gray'),
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
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view'   => Pages\ViewProduct::route('/{record}'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
