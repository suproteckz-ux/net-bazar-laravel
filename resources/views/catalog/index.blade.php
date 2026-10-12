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
                    @php $ancHref = !empty($ancestor->url_slug) ? route('catalog.show', ['slug' => $ancestor->url_slug]) : route('catalog.index', ['category' => $ancestor->id]); @endphp
                    <a href="{{ $ancHref }}">{{ $ancestor->name }}</a>
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
                        @php
                            $photo       = \App\Http\Controllers\CatalogController::firstPhoto($product);
                            $productPath = '/product/' . ($product->url_slug ?? rawurlencode($product->sku));
                            $waText      = 'Здравствуйте! Меня интересует товар: ' . $product->name . '. Ссылка: ' . config('netbazar.contact.site_url') . $productPath;
                            $waUrl       = 'https://wa.me/' . config('netbazar.contact.whatsapp') . '?text=' . rawurlencode($waText);
                        @endphp
                        <a href="{{ route('product.show', ['sku' => $product->url_slug ?? $product->sku]) }}"
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
                                    <span class="nb-card__wa-btn"
                                          role="button"
                                          tabindex="0"
                                          data-wa="{{ $waUrl }}"
                                          title="Написать в WhatsApp об этом товаре"
                                          aria-label="WhatsApp: {{ $product->name }}">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
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
