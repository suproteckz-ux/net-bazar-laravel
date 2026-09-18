@extends('layouts.app')

@section('title', 'Заказ оформлен — NetBazar')

@section('content')
<div class="nb-page">

    <div class="nb-checkout-done" style="max-width: 600px; margin: 0 auto; text-align: center; padding: 60px 0;">

        <svg width="56" height="56" viewBox="0 0 56 56" fill="none" aria-hidden="true" style="margin: 0 auto 24px;">
            <circle cx="28" cy="28" r="26" stroke="var(--nb-accent)" stroke-width="2"/>
            <path d="M17 28l8 8 14-16" stroke="var(--nb-accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <h1 class="nb-h1" style="margin-bottom: 8px;">Заказ оформлен!</h1>

        <p class="nb-body" style="font-size:22px; font-weight:800; color: var(--nb-accent); margin: 16px 0;">
            {{ $order->order_number }}
        </p>

        <p class="nb-body" style="margin-bottom: 32px;">
            Спасибо, {{ $order->customer_name }}! Ваш заказ принят.<br>
            Наш менеджер свяжется с вами по номеру <strong>{{ $order->phone }}</strong><br>
            для подтверждения и уточнения деталей доставки.
        </p>

        <div style="background: var(--nb-surface); border-radius: 12px; padding: 24px; text-align: left; margin-bottom: 32px;">
            <p class="nb-overline" style="margin-bottom: 12px;">Состав заказа</p>
            @foreach($order->items as $item)
                <div style="display:flex; justify-content:space-between; gap:12px; padding: 8px 0; border-bottom: 1px solid var(--nb-border);">
                    <span class="nb-body" style="font-size:13px; flex:1;">
                        {{ $item->name }} × {{ $item->qty }}
                    </span>
                    <span class="nb-price" style="font-size:14px; font-weight:700; white-space:nowrap;">
                        {{ number_format($item->subtotal_kzt, 0, '.', ' ') }} ₸
                    </span>
                </div>
            @endforeach
            <div style="display:flex; justify-content:space-between; margin-top: 12px;">
                <span class="nb-body" style="font-weight:700;">Итого</span>
                <span class="nb-price" style="font-size:18px; font-weight:900;">
                    {{ number_format($order->total_kzt, 0, '.', ' ') }} <span class="nb-cur">₸</span>
                </span>
            </div>
        </div>

        <a href="{{ route('home') }}" class="nb-btn nb-btn--primary">
            На главную
        </a>

    </div>

</div>
@endsection
