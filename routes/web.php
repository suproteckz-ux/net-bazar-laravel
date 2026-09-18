<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProductController;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Catalog
Route::redirect('/catalog/', '/catalog', 301)->name('catalog.slash');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{slug}', [CatalogController::class, 'show'])->name('catalog.show');

// Product — {sku} allows spaces, Cyrillic, hyphens (anything except /)
Route::get('/product/{sku}', [ProductController::class, 'show'])
    ->name('product.show')
    ->where('sku', '[^/]+');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{sku}', [CartController::class, 'update'])->name('cart.items.update')->where('sku', '[^/]+');
Route::delete('/cart/items/{sku}', [CartController::class, 'destroy'])->name('cart.items.destroy')->where('sku', '[^/]+');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

// Checkout
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/done/{id}', [CheckoutController::class, 'done'])->name('checkout.done');
