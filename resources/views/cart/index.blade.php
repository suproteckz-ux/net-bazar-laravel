@extends('layouts.app')

@section('title', 'Корзина — NetBazar')

@section('content')
<div class="nb-page">

    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <span aria-current="page">Корзина</span>
    </nav>

    <h1 class="nb-h1">Корзина</h1>

    @if(session('success'))
        <p class="nb-body" style="color: var(--nb-accent); margin-top: 12px;">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p class="nb-body" style="color: var(--nb-danger, #e55); margin-top: 12px;">{{ session('error') }}</p>
    @endif

    @if(empty($products))
        <p class="nb-body" style="margin-top: 32px;">Ваша корзина пуста.</p>
        <a href="{{ route('catalog.index') }}" class="nb-btn nb-btn--primary" style="margin-top: 24px; display: inline-flex;">
            Перейти в каталог
        </a>
    @else

        <div class="nb-cart" style="margin-top: 32px;">

            {{-- Cart items --}}
            @php $grandTotal = 0; @endphp
            @foreach($products as $sku => $row)
                @php $grandTotal += $row->line_total; @endphp
                <div class="nb-cart__row">
                    <a href="{{ route('product.show', ['sku' => $sku]) }}" class="nb-cart__thumb" aria-label="{{ $row->name }}">
                        @if($row->photo)
                            <img src="{{ $row->photo }}" alt="{{ $row->name }}" loading="lazy">
                        @else
                            <span class="nb-card__nophoto" style="font-size:10px;">НЕТ ФОТО</span>
                        @endif
                    </a>
                    <div class="nb-cart__info">
                        @if($row->brand)
                            <span class="nb-overline">{{ mb_strtoupper($row->brand) }}</span>
                        @endif
                        <a href="{{ route('product.show', ['sku' => $sku]) }}" class="nb-cart__name nb-body">{{ $row->name }}</a>
                        <span class="nb-cart__sku" style="font-size:12px; color:var(--nb-dim);">Арт.: {{ $sku }}</span>
                    </div>
                    <div class="nb-cart__qty">
                        <form method="POST" action="{{ route('cart.items.update', ['sku' => $sku]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="qty" value="{{ $row->qty }}" min="1" max="99"
                                   class="nb-cart__qty-input"
                                   onchange="this.form.submit()">
                        </form>
                    </div>
                    <div class="nb-cart__price">
                        <span class="nb-price" style="font-size:18px; font-weight:800;">
                            {{ number_format($row->line_total, 0, '.', ' ') }}
                            <span class="nb-cur">₸</span>
                        </span>
                        <span style="font-size:12px; color:var(--nb-dim); display:block;">
                            {{ number_format((float)$row->price_kzt, 0, '.', ' ') }} ₸ × {{ $row->qty }}
                        </span>
                    </div>
                    <form method="POST" action="{{ route('cart.items.destroy', ['sku' => $sku]) }}" class="nb-cart__del">
                        @csrf
                        @method('DELETE')
                        <button type="submit" aria-label="Удалить" title="Удалить">
                            <svg width="16" height="16" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 5h12M8 5V3h2v2M7 8v6M11 8v6M4 5l1 10h8l1-10" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>
                </div>
            @endforeach

            {{-- Summary --}}
            <div class="nb-cart__summary">
                <span class="nb-body">Итого:</span>
                <span class="nb-price" style="font-size:22px; font-weight:900;">
                    {{ number_format($grandTotal, 0, '.', ' ') }} <span class="nb-cur">₸</span>
                </span>
            </div>

            {{-- Actions --}}
            <div class="nb-cart__actions">
                <a href="{{ route('checkout.index') }}" class="nb-btn nb-btn--primary">
                    Оформить заказ
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <form method="POST" action="{{ route('cart.clear') }}" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="nb-btn nb-btn--secondary">Очистить корзину</button>
                </form>
            </div>

        </div>

    @endif

</div>
@endsection
