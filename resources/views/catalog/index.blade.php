@extends('layouts.app')

@section('title', $pageTitle . ' — NetBazar')

@section('content')
<div class="catalog-page">
    <div class="catalog-container">
        <div class="catalog-layout">

            {{-- ═══ LEFT SIDEBAR: category tree ═══ --}}
            <aside class="cat-sidebar" aria-label="Категории">
                {{-- Mobile toggle --}}
                <button class="cat-sidebar-toggle" aria-expanded="false" aria-controls="cat-sidebar-body"
                        onclick="this.setAttribute('aria-expanded', this.closest('.cat-sidebar').classList.toggle('is-open')); return false;">
                    <span>Категории</span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div class="cat-sidebar-body" id="cat-sidebar-body">
                    <p class="cat-sidebar-title">Категории</p>

                    {{-- "Все товары" top link --}}
                    <a class="cat-tree-all-link {{ !$categoryId && !$q ? 'is-active' : '' }}"
                       href="{{ route('catalog.index') }}">
                        Все товары
                    </a>

                    {{-- Hierarchical tree --}}
                    @include('partials.category-tree', ['nodes' => $categoryTree])
                </div>
            </aside>

            {{-- ═══ RIGHT MAIN: heading, search, grid, pagination ═══ --}}
            <section class="catalog-main" aria-label="Товары">

                <div class="catalog-heading">
                    <nav class="catalog-breadcrumb" aria-label="Путь">
                        <a href="{{ route('catalog.index') }}">Каталог</a>
                        @if($selectedCategory)
                            @php
                                $parts = explode(' / ', $selectedCategory->path);
                                $ids = [];
                                $pathSoFar = '';
                                $flatById = [];
                                foreach ($flatCategories as $fc) { $flatById[$fc->id] = $fc; }
                                // Walk ancestors for breadcrumb
                                $ancestors = [];
                                $cur = $selectedCategory;
                                while ($cur !== null) {
                                    array_unshift($ancestors, $cur);
                                    $cur = $flatById[$cur->parent_id] ?? null;
                                }
                            @endphp
                            @foreach($ancestors as $ancestor)
                                <span aria-hidden="true">›</span>
                                @if($ancestor->id === $selectedCategory->id)
                                    <span>{{ $ancestor->name }}</span>
                                @else
                                    <a href="{{ route('catalog.index', ['category' => $ancestor->id]) }}">{{ $ancestor->name }}</a>
                                @endif
                            @endforeach
                        @elseif($q)
                            <span aria-hidden="true">›</span>
                            <span>Поиск</span>
                        @endif
                    </nav>

                    <h1>{{ $pageTitle }}</h1>
                    @if($products->total() > 0)
                        <p class="catalog-count">{{ number_format($products->total(), 0, '.', ' ') }} товаров</p>
                    @endif
                </div>

                <form class="catalog-search-form" action="{{ route('catalog.index') }}" method="GET">
                    @if($categoryId)
                        <input type="hidden" name="category" value="{{ $categoryId }}">
                    @endif
                    <input type="search" name="q" value="{{ $q }}" placeholder="Поиск по каталогу...">
                    <button type="submit">Найти</button>
                </form>

                @if($products->isEmpty())
                    <p class="catalog-empty">По вашему запросу ничего не найдено.</p>
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
