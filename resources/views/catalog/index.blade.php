@extends('layouts.app')

@section('title', $pageTitle . ' — NetBazar')

@section('content')
<div class="nb-page">

    {{-- ── Breadcrumb ─────────────────────────────────────────────────────── --}}
    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        @if($selectedCategory)
            <a href="{{ route('catalog.index') }}">Каталог</a>
            @php
                $flatById = [];
                foreach ($flatCategories as $fc) { $flatById[$fc->id] = $fc; }
                $ancestors = [];
                $cur = $selectedCategory;
                while ($cur !== null) {
                    array_unshift($ancestors, $cur);
                    $cur = $flatById[$cur->parent_id ?? null] ?? null;
                }
            @endphp
            @foreach($ancestors as $ancestor)
                <span class="nb-sep" aria-hidden="true">›</span>
                @if($ancestor->id === $selectedCategory->id)
                    <span aria-current="page">{{ $ancestor->name }}</span>
                @else
                    <a href="{{ route('catalog.index', ['category' => $ancestor->id]) }}">{{ $ancestor->name }}</a>
                @endif
            @endforeach
        @elseif($q)
            <a href="{{ route('catalog.index') }}">Каталог</a>
            <span class="nb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Поиск</span>
        @else
            <span aria-current="page">Каталог</span>
        @endif
    </nav>

    {{-- ── Catalog grid: sidebar + main ──────────────────────────────────── --}}
    <div class="nb-catalog">

        {{-- Desktop sidebar (hidden at ≤1279px — category tree moves to drawer) --}}
        <aside class="nb-aside" aria-label="Категории">
            <p class="nb-aside__title nb-overline">Категории</p>
            <div class="nb-aside__rule"></div>

            <a href="{{ route('catalog.index') }}"
               class="nb-tree__all{{ (!$categoryId && !$q) ? ' is-active' : '' }}">
                Все товары
            </a>

            @if($categoryTree)
                <x-category-tree
                    :nodes="$categoryTree"
                    :active-path="$activeBranchIds ?? []"
                    :depth="1"
                />
            @endif
        </aside>

        {{-- Main content --}}
        <section aria-label="Товары">

            {{-- Heading --}}
            <h1 class="nb-h1">{{ $pageTitle }}</h1>
            @if($products->total() > 0)
                <p class="nb-count">{{ number_format($products->total(), 0, '.', ' ') }} товаров</p>
            @endif

            {{-- Filter / search bar --}}
            <div class="nb-filters">
                <form class="nb-search" action="{{ route('catalog.index') }}" method="GET" role="search">
                    @if($categoryId)
                        <input type="hidden" name="category" value="{{ $categoryId }}">
                    @endif
                    <input type="search" name="q" value="{{ $q }}"
                           placeholder="Поиск по каталогу..." aria-label="Поиск">
                    <button type="submit" aria-label="Найти">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none">
                            <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
                            <path d="M12 12l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </button>
                </form>
            </div>

            {{-- Product grid --}}
            @if($products->isEmpty())
                <p class="nb-body" style="margin-top: 32px;">По вашему запросу ничего не найдено.</p>
            @else
                <div class="nb-grid">
                    @foreach($products as $product)
                        @php $photo = \App\Http\Controllers\CatalogController::firstPhoto($product); @endphp
                        <a href="{{ route('product.show', ['sku' => $product->sku]) }}"
                           class="nb-card" aria-label="{{ $product->name }}">
                            <div class="nb-card__well">
                                @if($photo)
                                    <img src="{{ $photo }}" alt="{{ $product->name }}" loading="lazy">
                                @else
                                    <span class="nb-card__nophoto">НЕТ ФОТО</span>
                                @endif
                            </div>
                            <div class="nb-card__body">
                                <span class="nb-card__brand">{{ $product->brand ? mb_strtoupper($product->brand) : '' }}</span>
                                <h2 class="nb-card__title">{{ $product->name }}</h2>
                                <div class="nb-card__foot">
                                    <span class="nb-card__price nb-price">
                                        {{ number_format((float)$product->price_kzt, 0, '.', ' ') }}
                                        <span class="nb-cur">₸</span>
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($products->hasPages())
                    @php
                        $curPage   = $products->currentPage();
                        $lastPage  = $products->lastPage();
                        $rangeFrom = max(1, $curPage - 2);
                        $rangeTo   = min($lastPage, $curPage + 2);
                    @endphp
                    <nav class="nb-pager" aria-label="Страницы">
                        <span class="nb-pager__meta">
                            {{ number_format($products->firstItem(), 0, '.', ' ') }}–{{ number_format($products->lastItem(), 0, '.', ' ') }}
                            из {{ number_format($products->total(), 0, '.', ' ') }}
                        </span>
                        <ul class="nb-pager__list">
                            {{-- Prev --}}
                            @if(!$products->onFirstPage())
                                <li>
                                    <a class="nb-pager__link"
                                       href="{{ request()->fullUrlWithQuery(['page' => $curPage - 1]) }}"
                                       aria-label="Предыдущая страница">‹</a>
                                </li>
                            @endif
                            {{-- First + ellipsis --}}
                            @if($rangeFrom > 1)
                                <li><a class="nb-pager__link" href="{{ request()->fullUrlWithQuery(['page' => 1]) }}">1</a></li>
                                @if($rangeFrom > 2)
                                    <li><span class="nb-pager__gap">…</span></li>
                                @endif
                            @endif
                            {{-- Page range --}}
                            @for($page = $rangeFrom; $page <= $rangeTo; $page++)
                                <li>
                                    @if($page === $curPage)
                                        <span class="nb-pager__link is-current" aria-current="page">{{ $page }}</span>
                                    @else
                                        <a class="nb-pager__link" href="{{ request()->fullUrlWithQuery(['page' => $page]) }}">{{ $page }}</a>
                                    @endif
                                </li>
                            @endfor
                            {{-- Ellipsis + last --}}
                            @if($rangeTo < $lastPage)
                                @if($rangeTo < $lastPage - 1)
                                    <li><span class="nb-pager__gap">…</span></li>
                                @endif
                                <li><a class="nb-pager__link" href="{{ request()->fullUrlWithQuery(['page' => $lastPage]) }}">{{ $lastPage }}</a></li>
                            @endif
                            {{-- Next --}}
                            @if($products->hasMorePages())
                                <li>
                                    <a class="nb-pager__link"
                                       href="{{ request()->fullUrlWithQuery(['page' => $curPage + 1]) }}"
                                       aria-label="Следующая страница">›</a>
                                </li>
                            @endif
                        </ul>
                    </nav>
                @endif

            @endif
        </section>
    </div>
</div>
@endsection
