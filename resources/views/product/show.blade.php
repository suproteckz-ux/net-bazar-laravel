@extends('layouts.app')

@section('title', $product->name . ' — NetBazar')

@section('content')
<div class="nb-page">

    {{-- Breadcrumb --}}
    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <a href="{{ route('catalog.index') }}">Каталог</a>
        @if(!empty($kaspiCrumbs))
            @foreach($kaspiCrumbs as $crumb)
                <span class="nb-sep" aria-hidden="true">›</span>
                <a href="{{ route('catalog.index', ['category' => $crumb->id]) }}">{{ $crumb->name }}</a>
            @endforeach
        @elseif(!empty($product->category_name))
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

            @php
                $pdpSlug   = $product->url_slug ?? rawurlencode($product->sku);
                $pdpWaText = 'Здравствуйте! Меня интересует товар: ' . $product->name . '. Ссылка: ' . config('netbazar.contact.site_url') . '/product/' . $pdpSlug;
                $pdpWaUrl  = 'https://wa.me/' . config('netbazar.contact.whatsapp') . '?text=' . rawurlencode($pdpWaText);
            @endphp
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
                <a href="{{ $pdpWaUrl }}"
                   class="nb-btn nb-btn--whatsapp"
                   target="_blank" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
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
