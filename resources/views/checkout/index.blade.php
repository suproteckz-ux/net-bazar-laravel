@extends('layouts.app')

@section('title', 'Оформление заказа — NetBazar')

@section('content')
<div class="nb-page">

    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <a href="{{ route('cart.index') }}">Корзина</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <span aria-current="page">Оформление</span>
    </nav>

    <h1 class="nb-h1">Оформление заказа</h1>

    <div class="nb-checkout" style="margin-top: 32px;">

        {{-- Order form --}}
        <div class="nb-checkout__form">
            <form method="POST" action="{{ route('checkout.store') }}" novalidate>
                @csrf

                @if($errors->any())
                    <div style="color: var(--nb-danger, #e55); margin-bottom: 20px;">
                        <ul style="padding-left: 1.2em;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="nb-field">
                    <label class="nb-label" for="customer_name">Имя и фамилия *</label>
                    <input class="nb-input{{ $errors->has('customer_name') ? ' is-error' : '' }}"
                           type="text" id="customer_name" name="customer_name"
                           value="{{ old('customer_name') }}" required autocomplete="name">
                </div>

                <div class="nb-field">
                    <label class="nb-label" for="phone">Телефон *</label>
                    <input class="nb-input{{ $errors->has('phone') ? ' is-error' : '' }}"
                           type="tel" id="phone" name="phone"
                           value="{{ old('phone') }}" required autocomplete="tel"
                           placeholder="+7 (___) ___-__-__">
                </div>

                <div class="nb-field">
                    <label class="nb-label" for="city">Город *</label>
                    <input class="nb-input{{ $errors->has('city') ? ' is-error' : '' }}"
                           type="text" id="city" name="city"
                           value="{{ old('city') }}" required autocomplete="address-level2">
                </div>

                <div class="nb-field">
                    <label class="nb-label" for="comment">Комментарий к заказу</label>
                    <textarea class="nb-input" id="comment" name="comment" rows="3">{{ old('comment') }}</textarea>
                </div>

                <div class="nb-checkout__submit">
                    <button type="submit" class="nb-btn nb-btn--primary" style="width: 100%;">
                        Подтвердить заказ
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>

                <p class="nb-body" style="font-size: 12px; color: var(--nb-dim); margin-top: 12px;">
                    Нажимая «Подтвердить заказ», вы соглашаетесь с условиями покупки. Менеджер свяжется с вами для подтверждения.
                </p>
            </form>
        </div>

        {{-- Order summary --}}
        <aside class="nb-checkout__aside">
            <p class="nb-overline" style="margin-bottom: 12px;">Ваш заказ</p>
            <div class="nb-aside__rule" style="margin-bottom: 16px;"></div>

            @foreach($products as $row)
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px;">
                    <span class="nb-body" style="flex:1; font-size:13px; line-height:1.4;">
                        {{ $row->name }}
                        <span style="color:var(--nb-dim);">× {{ $row->qty }}</span>
                    </span>
                    <span class="nb-price" style="font-size:14px; font-weight:700; white-space:nowrap;">
                        {{ number_format($row->line_total, 0, '.', ' ') }} ₸
                    </span>
                </div>
            @endforeach

            <div class="nb-aside__rule" style="margin: 16px 0;"></div>

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span class="nb-body" style="font-weight:700;">Итого</span>
                <span class="nb-price" style="font-size:20px; font-weight:900;">
                    {{ number_format($subtotal, 0, '.', ' ') }} <span class="nb-cur">₸</span>
                </span>
            </div>
        </aside>

    </div>

</div>
@endsection
