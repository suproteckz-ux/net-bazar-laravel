<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeTileResource\Pages;
use App\Models\HomeTile;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;

class HomeTileResource extends Resource
{
    protected static ?string $model         = HomeTile::class;
    protected static ?string $label         = 'Плитка главной';
    protected static ?string $pluralLabel   = 'Категории на главной';
    protected static ?int    $navigationSort = 1;

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Витрина';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Основное')
                ->schema([
                    TextInput::make('label')
                        ->label('Название плитки')
                        ->required()
                        ->maxLength(120)
                        ->helperText('Отображается под изображением категории.'),

                    TextInput::make('sort_order')
                        ->label('Порядок')
                        ->numeric()
                        ->default(0)
                        ->helperText('Меньшее число — выше в строке.'),

                    Toggle::make('is_active')
                        ->label('Отображать на главной')
                        ->default(true),
                ])
                ->columns(3),

            Section::make('Ссылка')
                ->schema([
                    Select::make('link_type')
                        ->label('Тип категории')
                        ->options([
                            'kaspi_category'  => 'Kaspi-категория',
                            'manual_category' => 'Ручная категория',
                            'custom_url'      => 'Внутренняя ссылка',
                        ])
                        ->default('kaspi_category')
                        ->required()
                        ->live()
                        ->helperText('Выберите тип — ниже появится соответствующее поле.'),

                    Select::make('kaspi_category_id')
                        ->label('Kaspi-категория')
                        ->searchable()
                        ->options(function () {
                            if (!DbSchema::hasTable('kaspi_categories')) {
                                return [];
                            }
                            return DB::table('kaspi_categories')
                                ->orderBy('path')
                                ->limit(500)
                                ->pluck('path', 'id')
                                ->all();
                        })
                        ->helperText('Введите часть названия для поиска в списке.')
                        ->visible(fn ($get) => $get('link_type') === 'kaspi_category')
                        ->required(fn ($get) => $get('link_type') === 'kaspi_category'),

                    Select::make('manual_category_id')
                        ->label('Ручная категория')
                        ->relationship(name: 'manualCategory', titleAttribute: 'name')
                        ->searchable()
                        ->helperText('Только активные ручные категории.')
                        ->visible(fn ($get) => $get('link_type') === 'manual_category')
                        ->required(fn ($get) => $get('link_type') === 'manual_category'),

                    TextInput::make('custom_url')
                        ->label('Относительный URL')
                        ->placeholder('/catalog/chairs')
                        ->helperText('Только корневые пути: /catalog/..., /category/... Внешние и JS-ссылки запрещены.')
                        ->maxLength(500)
                        ->visible(fn ($get) => $get('link_type') === 'custom_url')
                        ->required(fn ($get) => $get('link_type') === 'custom_url')
                        ->rules(['nullable', 'regex:/^\\/[^\\/]/'])
                        ->validationMessages(['regex' => 'URL должен начинаться с / и не быть //']),
                ])
                ->columns(1),

            Section::make('Изображение')
                ->schema([
                    FileUpload::make('image_path')
                        ->label('Изображение плитки')
                        ->image()
                        ->disk('public')
                        ->directory('home-tiles')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->imagePreviewHeight('120')
                        ->helperText('JPEG, PNG или WebP. Макс. 2 МБ. Текущее изображение сохраняется при редактировании.')
                        ->nullable(),

                    TextInput::make('image_alt')
                        ->label('Alt-текст изображения')
                        ->maxLength(200)
                        ->helperText('Описание для поисковиков и скринридеров. Если пусто — используется название плитки.')
                        ->nullable(),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(40),
                Tables\Columns\TextColumn::make('label')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('link_type')
                    ->label('Тип')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'kaspi_category'  => 'Kaspi',
                        'manual_category' => 'Ручная',
                        'custom_url'      => 'URL',
                        default           => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        'kaspi_category'  => 'info',
                        'manual_category' => 'success',
                        'custom_url'      => 'warning',
                        default           => 'gray',
                    }),
                Tables\Columns\TextColumn::make('image_path')
                    ->label('Изображение')
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Изменена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHomeTiles::route('/'),
            'create' => Pages\CreateHomeTile::route('/create'),
            'edit'   => Pages\EditHomeTile::route('/{record}/edit'),
        ];
    }
}
