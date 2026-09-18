@extends('layouts.app')

@section('title', $categoryName . ' — NetBazar')

@section('content')
<div class="nb-page">

    {{-- Breadcrumb --}}
    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <a href="{{ route('catalog.index') }}">Каталог</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <span aria-current="page">{{ $categoryName }}</span>
    </nav>

    <div class="nb-catalog">

        {{-- Desktop sidebar --}}
        <aside class="nb-aside" aria-label="Категории">
            <p class="nb-aside__title nb-overline">Категории</p>
            <div class="nb-aside__rule"></div>

            <a href="{{ route('catalog.index') }}" class="nb-tree__all">
                Все товары
            </a>

            @if(!empty($categoryTree))
                <x-category-tree :nodes="$categoryTree" :active-path="[]" :depth="1" />
            @endif
        </aside>

        {{-- Main content --}}
        <section aria-label="Товары">

            <h1 class="nb-h1">{{ $categoryName }}</h1>
            @if($products->total() > 0)
                <p class="nb-count">{{ number_format($products->total(), 0, '.', ' ') }} товаров</p>
            @endif

            @if($products->isEmpty())
                <p class="nb-body" style="margin-top: 32px;">В этой категории пока нет товаров.</p>
            @else
                <div class="nb-grid" style="margin-top: 26px;">
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

                @if($products->hasPages())
                    @php
                        $curPage  = $products->currentPage();
                        $lastPage = $products->lastPage();
                        $rFrom    = max(1, $curPage - 2);
                        $rTo      = min($lastPage, $curPage + 2);
                    @endphp
                    <nav class="nb-pager" aria-label="Страницы">
                        <span class="nb-pager__meta">
                            {{ number_format($products->firstItem(), 0, '.', ' ') }}–{{ number_format($products->lastItem(), 0, '.', ' ') }}
                            из {{ number_format($products->total(), 0, '.', ' ') }}
                        </span>
                        <ul class="nb-pager__list">
                            @if(!$products->onFirstPage())
                                <li><a class="nb-pager__link" href="{{ $products->previousPageUrl() }}" aria-label="Предыдущая страница">‹</a></li>
                            @endif
                            @if($rFrom > 1)
                                <li><a class="nb-pager__link" href="{{ $products->url(1) }}">1</a></li>
                                @if($rFrom > 2)<li><span class="nb-pager__gap">…</span></li>@endif
                            @endif
                            @for($page = $rFrom; $page <= $rTo; $page++)
                                <li>
                                    @if($page === $curPage)
                                        <span class="nb-pager__link is-current" aria-current="page">{{ $page }}</span>
                                    @else
                                        <a class="nb-pager__link" href="{{ $products->url($page) }}">{{ $page }}</a>
                                    @endif
                                </li>
                            @endfor
                            @if($rTo < $lastPage)
                                @if($rTo < $lastPage - 1)<li><span class="nb-pager__gap">…</span></li>@endif
                                <li><a class="nb-pager__link" href="{{ $products->url($lastPage) }}">{{ $lastPage }}</a></li>
                            @endif
                            @if($products->hasMorePages())
                                <li><a class="nb-pager__link" href="{{ $products->nextPageUrl() }}" aria-label="Следующая страница">›</a></li>
                            @endif
                        </ul>
                    </nav>
                @endif
            @endif

        </section>
    </div>
</div>
@endsection
