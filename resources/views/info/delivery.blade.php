@extends('layouts.app')

@section('title', 'Доставка и оплата — NetBazar Алматы')
@section('meta_description', 'Доставка товаров NetBazar по Алматы и Казахстану. Самовывоз, доставка по Алматы от 2 000 ₸, бесплатная доставка при заказе свыше 20 000 ₸. Наличные, Kaspi QR, безналичная оплата.')

@section('content')
<div class="nb-page">

    {{-- Breadcrumb --}}
    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <span aria-current="page">Доставка и оплата</span>
    </nav>

    <div class="nb-info-page">

        <h1 class="nb-h1">Доставка и оплата</h1>
        <div class="nb-h2-rule"></div>

        {{-- Delivery options --}}
        <div class="nb-delivery-row" style="margin-top:32px;">
            {{-- Pickup --}}
            <div class="nb-delivery-card">
                <div class="nb-delivery-card__icon">
                    <svg width="32" height="32" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M18 4C12.477 4 8 8.477 8 14c0 8 10 18 10 18s10-10 10-18c0-5.523-4.477-10-10-10z"/>
                        <circle cx="18" cy="14" r="3.5" stroke-width="1.4"/>
                    </svg>
                </div>
                <div class="nb-delivery-card__title">Самовывоз из Алматы</div>
                <p class="nb-delivery-card__body">
                    Алматы, ул. Муратбаева, 138.<br>
                    В рабочее время, после подтверждения готовности товара.
                </p>
                <div class="nb-delivery-card__price" style="color:var(--nb-accent);">Бесплатно</div>
            </div>
            {{-- Almaty delivery --}}
            <div class="nb-delivery-card">
                <div class="nb-delivery-card__icon">
                    <svg width="32" height="32" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M3 24h22V10H3v14z"/>
                        <path d="M25 15h4l4 5v4h-8V15z"/>
                        <circle cx="9" cy="27" r="2.5" stroke-width="1.4"/>
                        <circle cx="28" cy="27" r="2.5" stroke-width="1.4"/>
                    </svg>
                </div>
                <div class="nb-delivery-card__title">Доставка по Алматы</div>
                <p class="nb-delivery-card__body">
                    Заказ до 11:00 — возможна доставка в тот же день.<br>
                    Заказ после 11:00 — обычно на следующий день.<br>
                    <strong style="color:var(--nb-text-2);">При заказе свыше 20&nbsp;000&nbsp;₸ — доставка бесплатно.</strong>
                </p>
                <div class="nb-delivery-card__price">2&nbsp;000 ₸ <span>до 20 000 ₸</span></div>
            </div>
        </div>

        {{-- Kazakhstan delivery --}}
        <div class="nb-section">
            <h2 class="nb-h2">Доставка по Казахстану</h2>
            <div class="nb-h2-rule"></div>
            <p class="nb-body">Отправляем товары в другие города Казахстана.</p>
            <div class="nb-delivery-row" style="margin-top:16px;">
                <div class="nb-delivery-card">
                    <div class="nb-delivery-card__title">Посылки до 3 кг</div>
                    <div class="nb-delivery-card__price">1&nbsp;500 ₸</div>
                </div>
                <div class="nb-delivery-card">
                    <div class="nb-delivery-card__title">Посылки свыше 3 кг</div>
                    <div class="nb-delivery-card__price" style="font-size:16px;font-family:var(--nb-font-text);font-weight:600;">Индивидуальный расчёт</div>
                </div>
            </div>
            <div class="nb-info-card" style="margin-top:14px;background:var(--nb-panel);border-color:var(--nb-accent);">
                <div class="nb-info-card__value" style="color:var(--nb-text-3);font-size:14px;">
                    Доставка в другие города осуществляется <strong style="color:#fff;">только после полной предоплаты</strong>.
                    Срок доставки зависит от населённого пункта и выбранной службы доставки.
                </div>
            </div>
        </div>

        {{-- Payment --}}
        <div class="nb-section">
            <h2 class="nb-h2">Способы оплаты</h2>
            <div class="nb-h2-rule"></div>
            <div class="nb-delivery-row nb-delivery-row--3" style="margin-top:20px;">
                <div class="nb-delivery-card">
                    <div class="nb-delivery-card__icon">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" stroke="var(--nb-accent)" stroke-width="1.6" aria-hidden="true">
                            <rect x="6" y="10" width="24" height="16" rx="2"/>
                            <path d="M6 16h24"/>
                            <path d="M12 22h4" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="nb-delivery-card__title">Наличные</div>
                    <p class="nb-delivery-card__body">При самовывозе и при доставке по Алматы.</p>
                </div>
                <div class="nb-delivery-card">
                    <div class="nb-delivery-card__icon">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" stroke="var(--nb-accent)" stroke-width="1.6" aria-hidden="true">
                            <rect x="8" y="6" width="20" height="24" rx="2"/>
                            <path d="M13 16h10M13 21h6" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="nb-delivery-card__title">Kaspi QR</div>
                    <p class="nb-delivery-card__body">Оплата через Kaspi QR, включая удалённую оплату по согласованию.</p>
                </div>
                <div class="nb-delivery-card">
                    <div class="nb-delivery-card__icon">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" stroke="var(--nb-accent)" stroke-width="1.6" aria-hidden="true">
                            <rect x="6" y="13" width="24" height="17" rx="2"/>
                            <path d="M13 13V9a2 2 0 012-2h6a2 2 0 012 2v4"/>
                            <path d="M6 21h24" stroke-width="1.4" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="nb-delivery-card__title">Безналичный расчёт</div>
                    <p class="nb-delivery-card__body">Для юридических лиц — счёт на оплату с документами. НДС 16%.</p>
                </div>
            </div>
            <div class="nb-delivery-row" style="margin-top:14px;">
                <div class="nb-delivery-card">
                    <div class="nb-delivery-card__title">Кредит и рассрочка</div>
                    <p class="nb-delivery-card__body">
                        Возможность оформления через Kaspi или Jusan. Доступность зависит от условий банка и выбранного товара.
                    </p>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="nb-section">
            <p class="nb-body">Если у вас остались вопросы по доставке или оплате — уточните у менеджера.</p>
            <div class="nb-contact-btns" style="margin-top:20px;">
                <a href="{{ config('netbazar.contact.phone_href') }}" class="nb-btn nb-btn--secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.08 1.18 2 2 0 012.07 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                    Позвонить
                </a>
                <a href="{{ config('netbazar.contact.whatsapp_url') }}"
                   class="nb-btn nb-btn--whatsapp"
                   target="_blank" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Написать в WhatsApp
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
