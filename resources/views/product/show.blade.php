@extends('layouts.app')

@section('title', $product->name . ' — NetBazar')

@section('content')
<div class="product-page">
    <div class="product-container">

        {{-- Breadcrumb --}}
        <nav class="product-breadcrumb" aria-label="Навигация">
            <a href="{{ route('home') }}">Главная</a>
            <span>›</span>
            <a href="{{ route('catalog.index') }}">Каталог</a>
            @if($product->category_name)
                <span>›</span>
                <span>{{ $product->category_name }}</span>
            @endif
            <span>›</span>
            <span>{{ $product->name }}</span>
        </nav>

        <div class="product-layout">

            {{-- Left: photo gallery --}}
            <div class="product-gallery">
                @if(count($photos) > 0)
                    <div class="product-main-photo">
                        <img id="main-photo" src="{{ $photos[0] }}" alt="{{ $product->name }}">
                    </div>
                    @if(count($photos) > 1)
                        <div class="product-thumbnails">
                            @foreach($photos as $i => $photo)
                                <button class="product-thumb {{ $i === 0 ? 'active' : '' }}"
                                        onclick="document.getElementById('main-photo').src='{{ $photo }}'; document.querySelectorAll('.product-thumb').forEach(t=>t.classList.remove('active')); this.classList.add('active');"
                                        aria-label="Фото {{ $i + 1 }}">
                                    <img src="{{ $photo }}" alt="{{ $product->name }} {{ $i + 1 }}" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="product-no-photo">
                        <span>Фото отсутствует</span>
                    </div>
                @endif
            </div>

            {{-- Right: product info --}}
            <div class="product-info">

                @if($product->brand)
                    <p class="product-page-brand">{{ $product->brand }}</p>
                @endif

                <h1 class="product-page-name">{{ $product->name }}</h1>

                <p class="product-page-sku">Арт.: {{ $product->sku }}</p>

                {{-- Stock status --}}
                @if($stockLabel)
                    @php
                        $stockClass = match($product->stock_state) {
                            'in_stock'     => 'stock-in',
                            'preorder'     => 'stock-pre',
                            'out_of_stock' => 'stock-out',
                            default        => '',
                        };
                    @endphp
                    <p class="product-stock {{ $stockClass }}">{{ $stockLabel }}</p>
                @endif

                {{-- Price --}}
                <div class="product-purchase">
                    <span class="product-page-price">
                        {{ number_format((float)$product->price_kzt, 0, '.', ' ') }} ₸
                    </span>

                    @if($product->kaspi_url)
                        <a href="{{ $product->kaspi_url }}" class="buy-button" target="_blank" rel="noopener">
                            Купить на Kaspi
                        </a>
                    @endif
                </div>

                {{-- Key attributes (first 5) --}}
                @if(count($attributes) > 0)
                    <dl class="product-key-facts">
                        @foreach(array_slice($attributes, 0, 6) as $attr)
                            <div>
                                <dt>{{ $attr['name'] }}</dt>
                                <dd>{{ $attr['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

            </div>
        </div>

        {{-- Description and full specs --}}
        @if($product->kaspi_description)
            <div class="product-description" id="description">
                <h2>Описание</h2>
                <div class="description-text">{{ $product->kaspi_description }}</div>
            </div>
        @endif

        @if(count($attributes) > 0)
            <div class="product-specs" id="specifications">
                <h2>Характеристики</h2>
                <dl class="specs-list">
                    @foreach($attributes as $attr)
                        <div class="spec-row">
                            <dt>{{ $attr['name'] }}</dt>
                            <dd>{{ $attr['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

    </div>
</div>
@endsection
