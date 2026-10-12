@extends('layouts.app')

@section('title', 'Контакты NetBazar — адрес, телефон и график работы в Алматы')
@section('meta_description', 'Контакты интернет-магазина NetBazar в Алматы. Адрес: Муратбаева, 138. Телефон +7 (778) 492-11-13. График работы, карта 2GIS, WhatsApp.')

@section('content')
@php $contact = config('netbazar.contact'); @endphp
<div class="nb-page">

    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <span aria-current="page">Контакты</span>
    </nav>

    <div class="nb-info-page">

        <h1 class="nb-h1">Контакты NetBazar</h1>
        <div class="nb-h2-rule"></div>

        {{-- ── 3 primary contact cards ─────────────────────────────────────── --}}
        <div class="nb-contact-primary">

            {{-- Phone --}}
            <div class="nb-contact-main-card">
                <div class="nb-contact-main-card__icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.08 1.18 2 2 0 012.07 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                </div>
                <div class="nb-contact-main-card__label">Телефон</div>
                <a href="{{ $contact['phone_href'] }}" class="nb-contact-main-card__value">{{ $contact['phone'] }}</a>
                <p style="font-size:13px;color:var(--nb-muted);margin:0;">Пн–Пт 09:00–18:00, Сб 11:00–16:00</p>
                <a href="{{ $contact['phone_href'] }}" class="nb-btn nb-btn--secondary nb-btn--sm" style="margin-top:8px;">Позвонить</a>
            </div>

            {{-- WhatsApp --}}
            <div class="nb-contact-main-card">
                <div class="nb-contact-main-card__icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </div>
                <div class="nb-contact-main-card__label">WhatsApp</div>
                <div class="nb-contact-main-card__value">{{ $contact['phone'] }}</div>
                <p style="font-size:13px;color:var(--nb-muted);margin:0;">Быстрый ответ на вопросы по товарам</p>
                <a href="{{ $contact['whatsapp_url'] }}" class="nb-btn nb-btn--whatsapp nb-btn--sm" target="_blank" rel="noopener noreferrer" style="margin-top:8px;">Написать в WhatsApp</a>
            </div>

            {{-- Address --}}
            <div class="nb-contact-main-card">
                <div class="nb-contact-main-card__icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <div class="nb-contact-main-card__label">Адрес магазина</div>
                <div class="nb-contact-main-card__value">{{ $contact['address_full'] }}</div>
                <p style="font-size:13px;color:var(--nb-muted);margin:0;">Самовывоз после подтверждения</p>
                <a href="https://go.2gis.com/mhnXg" class="nb-btn nb-btn--secondary nb-btn--sm" target="_blank" rel="noopener noreferrer" style="margin-top:8px;">
                    Открыть в 2GIS
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true" style="margin-left:4px;">
                        <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>

        </div>

        {{-- ── Location + Hours ──────────────────────────────────────────────── --}}
        <div class="nb-section">
            <div class="nb-location-grid">

                {{-- 2GIS block --}}
                <div>
                    <h2 class="nb-h2">Как нас найти</h2>
                    <div class="nb-h2-rule"></div>
                    <div class="nb-2gis-block">
                        <div class="nb-2gis-block__pin">
                            <svg width="36" height="36" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path d="M18 4C12.477 4 8 8.477 8 14c0 8 10 18 10 18s10-10 10-18c0-5.523-4.477-10-10-10z"/>
                                <circle cx="18" cy="14" r="4" stroke-width="1.4"/>
                            </svg>
                        </div>
                        <div class="nb-2gis-block__addr">{{ $contact['address_full'] }}</div>
                        <div class="nb-2gis-block__hint">
                            Магазин находится в Алматы, на ул. Муратбаева, 138.<br>
                            Самовывоз возможен в рабочее время после подтверждения наличия товара у менеджера.
                        </div>
                        <span class="nb-2gis-block__badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            Подтверждённая карточка 2GIS
                        </span>
                        <a href="https://go.2gis.com/mhnXg"
                           class="nb-btn nb-btn--primary"
                           target="_blank" rel="noopener noreferrer">
                            Открыть карту 2GIS
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Working hours --}}
                <div>
                    <h2 class="nb-h2">График работы</h2>
                    <div class="nb-h2-rule"></div>
                    <div class="nb-hours">
                        @foreach($contact['hours'] as $row)
                            <div class="nb-hours__row">
                                <span class="nb-hours__day">{{ $row['day'] }}</span>
                                <span class="nb-hours__time {{ $row['off'] ? 'nb-hours__time--off' : '' }}">{{ $row['time'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="nb-body" style="margin-top:16px;font-size:13.5px;">
                        Самовывоз — только в рабочее время и после подтверждения готовности товара менеджером.
                    </p>
                </div>

            </div>
        </div>

        {{-- ── Instagram + social ───────────────────────────────────────────── --}}
        <div class="nb-section">
            <h2 class="nb-h2">Мы в социальных сетях</h2>
            <div class="nb-h2-rule"></div>
            <div class="nb-info-cards" style="margin-top:16px;">
                <div class="nb-info-card">
                    <div class="nb-info-card__icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <rect x="2" y="2" width="20" height="20" rx="5"/>
                            <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </div>
                    <div class="nb-info-card__label">Instagram</div>
                    <div class="nb-info-card__value">
                        <a href="{{ $contact['instagram_url'] }}" target="_blank" rel="noopener noreferrer">{{ $contact['instagram'] }}</a>
                    </div>
                    <p class="nb-info-card__body">Фото товаров, акции и новинки</p>
                </div>
                <div class="nb-info-card">
                    <div class="nb-info-card__icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/>
                        </svg>
                    </div>
                    <div class="nb-info-card__label">Сайт</div>
                    <div class="nb-info-card__value">
                        <a href="{{ $contact['site_url'] }}">{{ parse_url($contact['site_url'], PHP_URL_HOST) }}</a>
                    </div>
                    <p class="nb-info-card__body">Весь каталог и актуальные цены</p>
                </div>
            </div>
        </div>

        {{-- ── Requisites ────────────────────────────────────────────────────── --}}
        <div class="nb-section">
            <h2 class="nb-h2">Реквизиты</h2>
            <div class="nb-h2-rule"></div>
            <dl class="nb-requisites" style="margin-top:18px;">
                <div class="nb-req-item">
                    <dt>Компания</dt>
                    <dd>{{ $contact['company'] }}</dd>
                </div>
                <div class="nb-req-item">
                    <dt>БИН</dt>
                    <dd>{{ $contact['bin'] }}</dd>
                </div>
                <div class="nb-req-item">
                    <dt>Адрес</dt>
                    <dd>{{ $contact['address_full'] }}</dd>
                </div>
                <div class="nb-req-item">
                    <dt>Телефон</dt>
                    <dd><a href="{{ $contact['phone_href'] }}" style="color:inherit;">{{ $contact['phone'] }}</a></dd>
                </div>
            </dl>
        </div>

    </div>
</div>
@endsection
