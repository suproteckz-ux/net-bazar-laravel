<?php

use Illuminate\Support\Facades\Route;
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
