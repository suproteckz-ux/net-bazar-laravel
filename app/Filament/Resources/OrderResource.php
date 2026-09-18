<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model       = Order::class;
    protected static ?string $label       = 'Заказ';
    protected static ?string $pluralLabel = 'Заказы';
    protected static ?int    $navigationSort = 1;

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Продажи';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Номер')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color())
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Покупатель')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон'),
                Tables\Columns\TextColumn::make('city')
                    ->label('Город'),
                Tables\Columns\TextColumn::make('total_kzt')
                    ->label('Сумма, ₸')
                    ->numeric(thousandsSeparator: ' ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(
                        collect(OrderStatus::cases())
                            ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])
                            ->all()
                    ),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Заказ')
                    ->schema([
                        TextEntry::make('order_number')->label('Номер заказа'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                            ->badge()
                            ->color(fn (OrderStatus $state) => $state->color()),
                        TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i'),
                        TextEntry::make('total_kzt')->label('Сумма, ₸')->numeric(thousandsSeparator: ' '),
                    ])
                    ->columns(2),
                Section::make('Покупатель')
                    ->schema([
                        TextEntry::make('customer_name')->label('Имя'),
                        TextEntry::make('phone')->label('Телефон'),
                        TextEntry::make('city')->label('Город'),
                        TextEntry::make('comment')->label('Комментарий')->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Состав заказа')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('sku')->label('Артикул'),
                                TextEntry::make('name')->label('Наименование'),
                                TextEntry::make('brand')->label('Бренд')->placeholder('—'),
                                TextEntry::make('qty')->label('Кол-во'),
                                TextEntry::make('price_kzt')->label('Цена, ₸')->numeric(thousandsSeparator: ' '),
                                TextEntry::make('subtotal_kzt')->label('Итого, ₸')->numeric(thousandsSeparator: ' '),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Внутренняя заметка')
                    ->schema([
                        TextEntry::make('internal_note')->label('Заметка')->placeholder('—'),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Изменить статус и заметку')
                    ->schema([
                        \Filament\Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options(
                                collect(OrderStatus::cases())
                                    ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])
                                    ->all()
                            )
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('internal_note')
                            ->label('Внутренняя заметка')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
