<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SatuRedirectController;

// ── Satu.kz legacy URL redirects ─────────────────────────────────────────────
// /p{satu_id}-{slug}.html  →  301  →  /product/{url_slug}
// /g{satu_id}-{slug}       →  301  →  /catalog/{slug} or /catalog?category={id}
// Populate the redirect tables with: php artisan netbazar:import-satu-redirects
Route::get('/p{satuId}-{rest}.html', [SatuRedirectController::class, 'product'])
    ->where(['satuId' => '[0-9]+', 'rest' => '[a-z0-9_-]+'])
    ->name('satu.product');
Route::get('/g{satuId}-{rest}', [SatuRedirectController::class, 'category'])
    ->where(['satuId' => '[0-9]+', 'rest' => '[a-z0-9_-]+'])
    ->name('satu.category');

// Sitemaps (cached 24 h, no Redis required)
Route::get('/sitemap.xml',                   [SitemapController::class, 'index'])     ->name('sitemap.index');
Route::get('/sitemap-pages.xml',             [SitemapController::class, 'pages'])     ->name('sitemap.pages');
Route::get('/sitemap-categories.xml',        [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-products-{page}.xml',   [SitemapController::class, 'products'])  ->name('sitemap.products')
    ->where('page', '[1-9][0-9]*');
// robots.txt served via Laravel so Sitemap URL always matches current site_url config
Route::get('/robots.txt',                    [SitemapController::class, 'robots'])    ->name('robots.txt');

Route::get('/', [HomeController::class, 'index'])->name('home');

// Catalog
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{slug}', [CatalogController::class, 'show'])->name('catalog.show');

// Product — {sku} allows spaces, Cyrillic, hyphens (anything except /)
Route::get('/product/{sku}', [ProductController::class, 'show'])
    ->name('product.show')
    ->where('sku', '[^/]+');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{sku}', [CartController::class, 'update'])
    ->name('cart.items.update')
    ->where('sku', '[^/]+');
Route::delete('/cart/items/{sku}', [CartController::class, 'destroy'])
    ->name('cart.items.destroy')
    ->where('sku', '[^/]+');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

// Info pages
Route::get('/about',            [InfoController::class, 'about'])->name('info.about');
Route::get('/contacts',         [InfoController::class, 'contacts'])->name('info.contacts');
Route::get('/delivery-payment', [InfoController::class, 'delivery'])->name('info.delivery');

// Checkout
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/done/{id}', [CheckoutController::class, 'done'])->name('checkout.done');