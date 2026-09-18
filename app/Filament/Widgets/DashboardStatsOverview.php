<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $newOrders   = Order::where('status', 'new')->count();
        $todayOrders = Order::whereDate('created_at', today())->count();
        $totalOrders = Order::count();
        $products    = Product::where('present', 1)->count();

        return [
            Stat::make('Новых заказов', $newOrders)
                ->description('Ожидают обработки')
                ->color($newOrders > 0 ? 'warning' : 'gray'),
            Stat::make('Заказов сегодня', $todayOrders),
            Stat::make('Заказов всего', $totalOrders),
            Stat::make('Активных товаров', number_format($products, 0, '.', ' ')),
        ];
    }
}
