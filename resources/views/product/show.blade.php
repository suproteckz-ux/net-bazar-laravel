@extends('layouts.app')

@section('title', $product->name . ' — NetBazar')

@section('content')
<div class="nb-page">

    {{-- Breadcrumb --}}
    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <a href="{{ route('catalog.index') }}">Каталог</a>
        @if(!empty($product->category_name))
            <span class="nb-sep" aria-hidden="true">›</span>
            <span>{{ $product->category_name }}</span>
        @endif
        <span class="nb-sep" aria-hidden="true">›</span>
        <span aria-current="page">{{ $product->name }}</span>
    </nav>

    {{-- ═══ Product grid: gallery left / info right ═══ --}}
    <div class="nb-product">

        {{-- ── Gallery ──────────────────────────────────────────── --}}
        <div>
            <div class="nb-gallery__main">
                {{-- Favourite placeholder (reserved) --}}
                <button class="nb-gallery__fav" aria-label="В избранное">
                    <svg width="20" height="20" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 18.5S3 13 3 7.5A4 4 0 0111 5.4 4 4 0 0119 7.5C19 13 11 18.5 11 18.5z" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                @if(count($photos) > 0)
                    <img id="nb-main-photo" src="{{ $photos[0] }}" alt="{{ $product->name }}">
                @else
                    <span style="font-size:13px;font-weight:700;letter-spacing:.14em;color:var(--nb-ghost);">НЕТ ФОТО</span>
                @endif
            </div>

            @if(count($photos) > 1)
                <div class="nb-gallery__thumbs">
                    @foreach($photos as $i => $photo)
                        <button class="nb-thumb{{ $i === 0 ? ' is-active' : '' }}"
                                data-photo="{{ $photo }}"
                                aria-label="Фото {{ $i + 1 }}">
                            <img src="{{ $photo }}" alt="{{ $product->name }} {{ $i + 1 }}" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Product info ──────────────────────────────────────── --}}
        <div>
            @if($product->brand)
                <p class="nb-pdp__brand">{{ mb_strtoupper($product->brand) }}</p>
            @endif

            <h1 class="nb-pdp__title">{{ $product->name }}</h1>

            <div class="nb-pdp__meta">
                <span>Арт.: <b>{{ $product->sku }}</b></span>
            </div>

            @if($stockLabel)
                @php
                    $stockStyle = match($product->stock_state ?? '') {
                        'in_stock'     => '',
                        'preorder'     => 'color: var(--nb-accent)',
                        'out_of_stock' => 'color: var(--nb-dim)',
                        default        => 'color: var(--nb-dim)',
                    };
                @endphp
                <p class="nb-pdp__stock" style="{{ $stockStyle }}">{{ $stockLabel }}</p>
            @endif

            <div class="nb-rule"></div>

            <p class="nb-pdp__price nb-price">
                {{ number_format((float)$product->price_kzt, 0, '.', ' ') }}
                <span class="nb-cur">₸</span>
            </p>

            <div class="nb-pdp__actions">
                @if($product->kaspi_confirmed_url)
                    <a href="{{ $product->kaspi_confirmed_url }}?m={{ config('netbazar.kaspi_partner_id') }}"
                       class="nb-btn nb-btn--primary"
                       target="_blank" rel="noopener noreferrer">
                        Купить на Kaspi
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                @endif
                <form method="POST" action="{{ route('cart.items.store') }}" class="nb-cart-form">
                    @csrf
                    <input type="hidden" name="sku" value="{{ $product->sku }}">
                    <input type="hidden" name="qty" value="1">
                    <button class="nb-btn nb-btn--secondary" type="submit">
                        В корзину
                    </button>
                </form>
            </div>

            {{-- Key attributes (first 6) --}}
            @if(count($attributes) > 0)
                <dl class="nb-attrs">
                    @foreach(array_slice($attributes, 0, 6) as $attr)
                        <div class="nb-attrs__row">
                            <dt>{{ $attr['name'] }}</dt>
                            <dd>{{ $attr['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>

    {{-- ═══ Lower section: description + full specs ═══ --}}
    @if($product->kaspi_description || count($attributes) > 0)
        <div class="nb-pdp__lower">

            {{-- Description --}}
            <div>
                @if($product->kaspi_description)
                    <h2 class="nb-h2" id="description">Описание</h2>
                    <div class="nb-h2-rule"></div>
                    <p class="nb-body">{{ $product->kaspi_description }}</p>
                @endif

                {{-- Full specifications --}}
                @if(count($attributes) > 0)
                    <h2 class="nb-h2" id="specifications" style="margin-top: 48px;">Характеристики</h2>
                    <div class="nb-h2-rule"></div>
                    <dl class="nb-specs">
                        @foreach($attributes as $attr)
                            <div class="nb-specs__row">
                                <dt>{{ $attr['name'] }}</dt>
                                <dd>{{ $attr['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            {{-- Reserved slot (future: delivery/warranty info) --}}
            <div class="nb-reserved" aria-hidden="true" style="display:none;"></div>

        </div>
    @endif

</div>

{{-- ═══ Mobile sticky buy bar ═══ --}}
<div class="nb-pdp__buybar" role="complementary" aria-label="Купить">
    <span class="nb-price" style="font-size: 20px; font-weight: 800;">
        {{ number_format((float)$product->price_kzt, 0, '.', ' ') }} <span class="nb-cur">₸</span>
    </span>
    @if($product->kaspi_confirmed_url)
        <a href="{{ $product->kaspi_confirmed_url }}?m={{ config('netbazar.kaspi_partner_id') }}"
           class="nb-btn nb-btn--primary"
           target="_blank" rel="noopener noreferrer">
            Купить на Kaspi
        </a>
    @endif
</div>

@endsection
