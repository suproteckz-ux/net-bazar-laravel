<?php

namespace App\Providers;

use App\Livewire\DiagnosticHandleRequests;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\HandleRequests\HandleRequests as LivewireHandleRequests;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // TEMPORARY DIAGNOSTIC — replace Livewire's HandleRequests with a subclass
        // that logs the TypeError Livewire silently converts to HTTP 419 in production.
        // Remove once the root cause is identified (see DiagnosticHandleRequests.php).
        $this->app->instance(LivewireHandleRequests::class, new DiagnosticHandleRequests());

        // Share total cart quantity (sum of all item qtys) with every view
        View::composer('*', function ($view) {
            $cart      = session('cart', []);
            $cartCount = array_sum(array_column($cart, 'qty'));
            $view->with('cartCount', $cartCount);
        });
    }
}
