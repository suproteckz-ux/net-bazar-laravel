@extends('layouts.app')

@section('title', $categoryName . ' — NetBazar')

@section('content')
<div class="catalog-page">
    <div class="catalog-container">
        <div class="catalog-layout">

            {{-- ═══ LEFT SIDEBAR: category tree ═══ --}}
            <aside class="cat-sidebar" aria-label="Категории">
                <button class="cat-sidebar-toggle" aria-expanded="false" aria-controls="cat-sidebar-body-show"
                        onclick="this.setAttribute('aria-expanded', this.closest('.cat-sidebar').classList.toggle('is-open')); return false;">
                    <span>Категории</span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div class="cat-sidebar-body" id="cat-sidebar-body-show">
                    <p class="cat-sidebar-title">Категории</p>

                    <a class="cat-tree-all-link"
                       href="{{ route('catalog.index') }}">
                        Все товары
                    </a>

                    @include('partials.category-tree', ['nodes' => $categoryTree])
                </div>
            </aside>

            {{-- ═══ RIGHT MAIN ═══ --}}
            <section class="catalog-main" aria-label="Товары">

                <div class="catalog-heading">
                    <nav class="catalog-breadcrumb" aria-label="Путь">
                        <a href="{{ route('catalog.index') }}">Каталог</a>
                        <span aria-hidden="true">›</span>
                        <span>{{ $categoryName }}</span>
                    </nav>
                    <h1>{{ $categoryName }}</h1>
                    @if($products->total() > 0)
                        <p class="catalog-count">{{ number_format($products->total(), 0, '.', ' ') }} товаров</p>
                    @endif
                </div>

                @if($products->isEmpty())
                    <p class="catalog-empty">В этой категории пока нет товаров.</p>
                @else
                    <div class="product-grid">
                        @foreach($products as $product)
                            @php $photo = \App\Http\Controllers\CatalogController::firstPhoto($product); @endphp
                            <a href="{{ route('product.show', ['sku' => $product->sku]) }}" class="product-card">
                                <div class="product-card-photo">
                                    @if($photo)
                                        <img src="{{ $photo }}" alt="{{ $product->name }}" loading="lazy">
                                    @else
                                        <span class="no-photo">Нет фото</span>
                                    @endif
                                </div>
                                <div class="product-card-body">
                                    @if($product->brand)
                                        <span class="product-brand">{{ $product->brand }}</span>
                                    @endif
                                    <h2 class="product-name">{{ $product->name }}</h2>
                                    <span class="product-price">{{ number_format((float)$product->price_kzt, 0, '.', ' ') }} ₸</span>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="pagination-wrap">
                        {{ $products->links('pagination::simple-bootstrap-5') }}
                    </div>
                @endif

            </section>
        </div>
    </div>
</div>
@endsection
